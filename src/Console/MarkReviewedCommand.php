<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Console;

use HtmlTranslator\Filament\Console\Concerns\SelectsRecords;
use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Enums\TranslationStatus;
use HtmlTranslator\Filament\Models\TranslationState;
use HtmlTranslator\Filament\Support\RelatedRecords;
use HtmlTranslator\Filament\Support\TranslationLocales;
use Illuminate\Auth\AuthManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Marks the translations of many records as reviewed, on behalf of a named reviewer. Reviewing stays an explicit
 * step: it asks for confirmation, and stale translations (source changed since) are left out unless
 * --include-stale says they were checked against the current source.
 */
final class MarkReviewedCommand extends Command
{
    use SelectsRecords;

    /** @var string */
    protected $signature = 'html-translator-filament:mark-reviewed
        {models* : Model classes or morph aliases, e.g. "App\Models\Post"}
        {--reviewer= : The user who reviewed the translations: user ID or e-mail address (required)}
        {--locale=* : Target locale; repeatable (default: every target locale of the plugin)}
        {--relations=* : Relations reviewed together with each record; repeatable}
        {--id=* : Only these record keys; repeatable}
        {--include-stale : Also mark stale translations (source changed since), after checking them}
        {--dry-run : List what would be marked; change nothing}
        {--force : Mark without asking for confirmation}';

    /** @var string */
    protected $description = 'Mark translations as reviewed on behalf of a reviewer';

    public function handle(TranslationLocales $locales, TranslationStateStore $states, AuthManager $auth, ConnectionResolverInterface $database): int
    {
        /** @var list<string> $relations */
        $relations = array_values(array_filter((array) $this->option('relations'), \is_string(...)));
        $includeStale = (bool) $this->option('include-stale');

        try {
            $models = $this->models();
            $targets = $this->targets($locales);
            $reviewer = $this->reviewer($auth);
            $plan = $this->plan($models, $targets, $relations, $includeStale, $states);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->report($plan, $includeStale, (bool) $this->option('dry-run'));

        if ((bool) $this->option('dry-run')) {
            return self::SUCCESS;
        }

        $count = array_sum(array_map(static fn(array $item): int => \count($item['marked']), $plan));
        if ($count === 0) {
            $this->info('Nothing to mark.');

            return self::SUCCESS;
        }

        if (! $this->confirmMarking($count, $reviewer)) {
            return self::FAILURE;
        }

        $reviewerId = $this->reviewerId($reviewer);
        $marked = 0;
        foreach ($plan as $item) {
            if ($item['marked'] === []) {
                continue;
            }

            // One transaction per record: an interrupted run leaves whole records marked or not.
            $marked += $database->connection()->transaction(static function () use ($item, $states, $reviewerId, $includeStale): int {
                $sum = 0;
                foreach ($item['models'] as $model) {
                    $sum += $states->markReviewedAll($model, $item['locale'], $reviewerId, $includeStale);
                }

                return $sum;
            });
        }

        $this->info(\sprintf('Marked as reviewed: %d', $marked));

        return self::SUCCESS;
    }

    /**
     * Per record and locale: the record with its related records, the translations that will be marked, and the
     * stale ones left out.
     *
     * @param list<class-string<Model&TracksTranslations>> $models
     * @param list<string> $targets
     * @param list<string> $relations
     *
     * @return list<array{record: Model, locale: string, models: list<Model&TracksTranslations>, marked: list<array{Model, TranslationState}>, skipped: list<array{Model, TranslationState}>}>
     */
    private function plan(array $models, array $targets, array $relations, bool $includeStale, TranslationStateStore $states): array
    {
        $plan = [];
        foreach ($models as $model) {
            foreach ($this->query($model)->lazyById() as $record) {
                $withRelated = [$record, ...RelatedRecords::of($record, $relations)];
                foreach ($targets as $locale) {
                    $marked = [];
                    $skipped = [];
                    foreach ($withRelated as $member) {
                        $all = $states->reviewable($member, $locale, includeStale: true);
                        $chosen = $includeStale ? $all : $states->reviewable($member, $locale, includeStale: false);
                        $chosenIds = array_map(static fn(TranslationState $state): mixed => $state->getKey(), $chosen);
                        foreach ($all as $state) {
                            if (\in_array($state->getKey(), $chosenIds, true)) {
                                $marked[] = [$member, $state];
                            } else {
                                $skipped[] = [$member, $state];
                            }
                        }
                    }

                    $plan[] = ['record' => $record, 'locale' => $locale, 'models' => $withRelated, 'marked' => $marked, 'skipped' => $skipped];
                }
            }
        }

        return $plan;
    }

    /**
     * @param list<array{record: Model, locale: string, models: list<Model&TracksTranslations>, marked: list<array{Model, TranslationState}>, skipped: list<array{Model, TranslationState}>}> $plan
     */
    private function report(array $plan, bool $includeStale, bool $dryRun): void
    {
        $verbosity = $dryRun ? null : 'v';
        $marked = 0;
        $stale = 0;
        $skipped = 0;
        foreach ($plan as $item) {
            $marked += \count($item['marked']);
            $skipped += \count($item['skipped']);
            foreach ($item['marked'] as [$model, $state]) {
                if ($state->status !== TranslationStatus::Missing && $state->source_hash !== $this->currentHash($model, $state)) {
                    ++$stale;
                }
            }

            if ($item['marked'] !== []) {
                $this->line($this->describe($item['locale'], $item['marked']), verbosity: $verbosity);
            }

            if ($item['skipped'] !== []) {
                $this->warn($this->describe($item['locale'], $item['skipped']) . ' (stale, not marked)');
            }
        }

        $this->line(\sprintf('To mark as reviewed: %d', $marked));
        if ($includeStale && $stale > 0) {
            $this->line(\sprintf('Of these, stale: %d (they will be accepted for the current source text)', $stale));
        }

        if ($skipped > 0) {
            $this->warn(\sprintf(
                'Stale, not marked: %d (the source text changed after translation; check them, then run again with --include-stale)',
                $skipped,
            ));
        }
    }

    /**
     * "Workbench\App\Models\Post #1 (en): title, body", one line per record of the group.
     *
     * @param list<array{Model, TranslationState}> $items
     */
    private function describe(string $locale, array $items): string
    {
        $byRecord = [];
        foreach ($items as [$model, $state]) {
            $byRecord[$this->label($model)][] = $state->attribute;
        }

        $lines = [];
        foreach ($byRecord as $label => $attributes) {
            $lines[] = \sprintf('%s (%s): %s', $label, $locale, implode(', ', $attributes));
        }

        return implode(\PHP_EOL, $lines);
    }

    private function currentHash(Model $model, TranslationState $state): ?string
    {
        if (! $model instanceof TracksTranslations) {
            return null;
        }

        $source = $model->getTranslation($state->attribute, app(TranslationLocales::class)->source(), false);

        return TranslationState::hashSource(\is_string($source) ? $source : null);
    }

    private function reviewer(AuthManager $auth): Authenticatable
    {
        $value = $this->option('reviewer');
        if (! \is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('Name the reviewer with --reviewer=<user ID or e-mail address>.');
        }

        $guard = $auth->guard();
        $provider = method_exists($guard, 'getProvider') ? $guard->getProvider() : null;
        if (! $provider instanceof UserProvider) {
            throw new InvalidArgumentException('The default auth guard has no user provider, so the reviewer cannot be looked up.');
        }

        $user = ctype_digit($value) ? $provider->retrieveById((int) $value) : $provider->retrieveByCredentials(['email' => $value]);

        return $user ?? throw new InvalidArgumentException(\sprintf('No user "%s" was found.', $value));
    }

    private function reviewerId(Authenticatable $reviewer): ?int
    {
        $id = $reviewer->getAuthIdentifier();

        return \is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    private function confirmMarking(int $count, Authenticatable $reviewer): bool
    {
        if ((bool) $this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Marking translations as reviewed needs a confirmation; add --force to run it without interaction.');

            return false;
        }

        $name = $reviewer instanceof Model && \is_string($reviewer->getAttribute('name')) ? $reviewer->getAttribute('name') : (string) $this->option('reviewer');

        return $this->confirm(\sprintf('Mark %d translation(s) as reviewed by %s?', $count, $name));
    }
}

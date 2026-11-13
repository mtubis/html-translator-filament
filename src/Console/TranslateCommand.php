<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Console;

use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Enums\OverwritePolicy;
use HtmlTranslator\Filament\Exceptions\InvalidPluginConfigurationException;
use HtmlTranslator\Filament\Jobs\TranslateRecordJob;
use HtmlTranslator\Filament\Support\TranslationDefaults;
use HtmlTranslator\Filament\Support\TranslationLocales;
use HtmlTranslator\Filament\Translation\AttributeOutcome;
use HtmlTranslator\Filament\Translation\OutcomeStatus;
use HtmlTranslator\Filament\Translation\TranslateRecord;
use HtmlTranslator\Filament\Translation\TranslationBatch;
use HtmlTranslator\Filament\Translation\TranslationReport;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * Translates records of one or more models from the command line: synchronously, as a dry run, or as a batch of
 * queued jobs. A thin layer over TranslateRecord / TranslateRecordJob, with the plugin's locales and defaults.
 * Output contains counts, usage and the translator's issue messages only; never source texts, translations or keys.
 */
final class TranslateCommand extends Command
{
    private const array POLICIES = [
        'only-missing' => OverwritePolicy::OnlyMissing,
        'overwrite-machine' => OverwritePolicy::OverwriteMachine,
        'force' => OverwritePolicy::Force,
    ];

    /** @var string */
    protected $signature = 'html-translator-filament:translate
        {models* : Model classes or morph aliases, e.g. "App\Models\Post"}
        {--locale=* : Target locale; repeatable (default: every target locale of the plugin)}
        {--policy=only-missing : only-missing, overwrite-machine or force}
        {--relations=* : Relations translated together with each record; repeatable}
        {--id=* : Only these record keys; repeatable}
        {--queue : Dispatch a batch of queued jobs instead of translating in this process}
        {--dry-run : Show what would be translated; no provider is called, nothing is written}
        {--provider= : A provider entry of config/html-translator.php (not with --queue)}
        {--profile= : A profile entry of config/html-translator.php (not with --queue)}
        {--fail-on-incomplete : Exit with 1 when a translation is incomplete}
        {--force : Overwrite reviewed translations with --policy=force without asking}';

    /** @var string */
    protected $description = 'Translate translatable records into the target locales of the html-translator plugin';

    public function handle(TranslationLocales $locales, TranslationDefaults $defaults, TranslateRecord $translate, TranslationBatch $batches): int
    {
        try {
            $models = $this->models();
            $targets = $this->targets($locales);
            $policy = $this->policy();
            $this->assertOverrides();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        /** @var list<string> $relations */
        $relations = array_values(array_filter((array) $this->option('relations'), \is_string(...)));
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && $policy === OverwritePolicy::Force && ! $this->confirmForce()) {
            return self::FAILURE;
        }

        if ($dryRun) {
            return $this->preview($models, $targets, $policy, $relations, $translate);
        }

        if ((bool) $this->option('queue')) {
            return $this->dispatch($models, $targets, $policy, $relations, $batches, $defaults);
        }

        return $this->withOverrides($defaults, fn(): int => $this->translate($models, $targets, $policy, $relations, $translate));
    }

    /**
     * @param list<class-string<Model&TracksTranslations>> $models
     * @param list<string> $targets
     * @param list<string> $relations
     */
    private function translate(array $models, array $targets, OverwritePolicy $policy, array $relations, TranslateRecord $translate): int
    {
        $reports = [];
        foreach ($models as $model) {
            foreach ($targets as $locale) {
                $this->line(\sprintf('Translating %s into %s', $model, $locale));
                // Only on a terminal: written to a log (cron, CI, a container without TTY) its frames run together.
                $bar = $this->output->isDecorated() ? $this->output->createProgressBar($this->query($model)->count()) : null;
                foreach ($this->query($model)->lazyById() as $record) {
                    try {
                        $report = $translate($record, $locale, $policy, $relations);
                    } catch (InvalidArgumentException $exception) {
                        $bar?->clear();
                        $this->error($exception->getMessage());

                        return self::FAILURE;
                    }

                    $reports[] = $report;
                    if ($report->failed() + $report->incomplete() > 0) {
                        $bar?->clear();
                        $this->reportProblems($record, $locale, $report);
                        $bar?->display();
                    }

                    $bar?->advance();
                }

                if ($bar !== null) {
                    $bar->finish();
                    $this->newLine();
                }
            }
        }

        $total = TranslationReport::merge(...$reports);
        $this->summary($total);

        if ($total->failed() > 0 || ($total->incomplete() > 0 && (bool) $this->option('fail-on-incomplete'))) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param list<class-string<Model&TracksTranslations>> $models
     * @param list<string> $targets
     * @param list<string> $relations
     */
    private function preview(array $models, array $targets, OverwritePolicy $policy, array $relations, TranslateRecord $translate): int
    {
        $count = 0;
        foreach ($models as $model) {
            foreach ($targets as $locale) {
                foreach ($this->query($model)->lazyById() as $record) {
                    try {
                        $outcomes = $translate->preview($record, $locale, $policy, $relations);
                    } catch (InvalidArgumentException $exception) {
                        $this->error($exception->getMessage());

                        return self::FAILURE;
                    }

                    $planned = array_filter($outcomes, static fn(AttributeOutcome $o): bool => $o->status === OutcomeStatus::Translated);
                    $count += \count($planned);
                    if ($planned !== []) {
                        $this->line(\sprintf('%s (%s): %s', $this->label($record), $locale, implode(', ', array_map(
                            static fn(AttributeOutcome $o): string => self::attributeLabel($record, $o),
                            $planned,
                        ))), verbosity: 'v');
                    }
                }
            }
        }

        $this->info(\sprintf('Would translate: %d attribute(s). Nothing was written.', $count));

        return self::SUCCESS;
    }

    /**
     * @param list<class-string<Model&TracksTranslations>> $models
     * @param list<string> $targets
     * @param list<string> $relations
     */
    private function dispatch(array $models, array $targets, OverwritePolicy $policy, array $relations, TranslationBatch $batches, TranslationDefaults $defaults): int
    {
        $jobs = [];
        foreach ($models as $model) {
            foreach ($targets as $locale) {
                foreach ($this->query($model)->lazyById() as $record) {
                    $jobs[] = new TranslateRecordJob($record, $locale, $policy, $relations, notify: false);
                }
            }
        }

        if ($jobs === []) {
            $this->info('Nothing to translate.');

            return self::SUCCESS;
        }

        $batch = $batches->make($jobs, \count($jobs) . ' jobs into ' . implode(', ', $targets) . ' (console)')->dispatch();

        $connection = $defaults->connection() ?? $this->configString('queue.default', 'sync');
        $queue = $defaults->queueName() ?? $this->configString("queue.connections.{$connection}.queue", 'default');
        $this->info(\sprintf('Dispatched %d job(s), batch %s.', \count($jobs), $batch->id));
        $this->line(\sprintf(
            'Make sure a worker runs: php artisan queue:work %s --queue=%s',
            $connection,
            $queue === 'default' ? 'default' : $queue . ',default',
        ));

        return self::SUCCESS;
    }

    private function reportProblems(Model $record, string $locale, TranslationReport $report): void
    {
        foreach ($report->outcomes() as $outcome) {
            if ($outcome->status !== OutcomeStatus::Failed && $outcome->status !== OutcomeStatus::Incomplete) {
                continue;
            }

            $this->warn(\sprintf('%s (%s): %s %s', $this->label($record), $locale, self::attributeLabel($record, $outcome), strtolower($outcome->status->name)));
            foreach ($outcome->issues as $issue) {
                $this->line('  ' . $issue, verbosity: 'v');
            }
        }
    }

    private function summary(TranslationReport $report): void
    {
        $stats = $report->stats();
        $this->info(\sprintf(
            'Translated: %d · Skipped: %d · Incomplete: %d · Failed: %d',
            $report->translated(),
            $report->skipped(),
            $report->incomplete(),
            $report->failed(),
        ));
        $this->line(\sprintf(
            'Units: %d · Batches: %d · Input tokens: %s · Output tokens: %s · Cache hits: %d',
            $stats['units'],
            $stats['batches'],
            $stats['input_tokens'] ?? 'n/a',
            $stats['output_tokens'] ?? 'n/a',
            $stats['cache_hits'],
        ));
    }

    /**
     * @return list<class-string<Model&TracksTranslations>>
     */
    private function models(): array
    {
        $models = [];
        foreach ((array) $this->argument('models') as $name) {
            if (! \is_string($name)) {
                continue;
            }

            $class = Relation::getMorphedModel($name) ?? $name;
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                throw new InvalidArgumentException(\sprintf('"%s" is neither an Eloquent model class nor a morph alias.', $name));
            }

            if (! is_subclass_of($class, TracksTranslations::class)) {
                throw new InvalidArgumentException(\sprintf('%s does not implement %s.', $class, TracksTranslations::class));
            }

            /** @var class-string<Model&TracksTranslations> $class */
            $models[] = $class;
        }

        return $models;
    }

    /**
     * @return list<string>
     */
    private function targets(TranslationLocales $locales): array
    {
        if (! $locales->isConfigured()) {
            throw new InvalidArgumentException('The html-translator plugin is not registered on a Filament panel, so it has no locales.');
        }

        $requested = array_values(array_filter((array) $this->option('locale'), \is_string(...)));
        foreach ($requested as $locale) {
            if (! \in_array($locale, $locales->targets(), true)) {
                throw new InvalidArgumentException(InvalidPluginConfigurationException::notATargetLocale($locale, $locales->targets())->getMessage());
            }
        }

        return $requested === [] ? $locales->targets() : array_values(array_unique($requested));
    }

    private function policy(): OverwritePolicy
    {
        $name = $this->option('policy');

        return self::POLICIES[\is_string($name) ? $name : ''] ?? throw new InvalidArgumentException(\sprintf(
            'Unknown --policy "%s"; use %s.',
            \is_string($name) ? $name : '',
            implode(', ', array_keys(self::POLICIES)),
        ));
    }

    private function assertOverrides(): void
    {
        if ((bool) $this->option('queue') && ($this->option('provider') !== null || $this->option('profile') !== null)) {
            throw new InvalidArgumentException('--provider and --profile apply to a synchronous run only; queued jobs use the plugin\'s settings.');
        }
    }

    private function confirmForce(): bool
    {
        if ((bool) $this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->error('--policy=force overwrites reviewed translations; add --force to run it without interaction.');

            return false;
        }

        return $this->confirm('Reviewed translations will be overwritten. Continue?');
    }

    /**
     * Runs $run with --provider / --profile applied to the plugin's defaults (so the states record them too), and
     * restores the defaults afterwards.
     *
     * @param callable(): int $run
     */
    private function withOverrides(TranslationDefaults $defaults, callable $run): int
    {
        $provider = $this->option('provider');
        $profile = $this->option('profile');
        if (! \is_string($provider) && ! \is_string($profile)) {
            return $run();
        }

        $previous = [$defaults->profile(), $defaults->provider(), $defaults->queue(), $defaults->connection(), $defaults->queueName(), $defaults->rateLimit()];
        $defaults->configure(
            \is_string($profile) ? $profile : $previous[0],
            \is_string($provider) ? $provider : $previous[1],
            $previous[2],
            $previous[3],
            $previous[4],
            $previous[5],
        );

        try {
            return $run();
        } finally {
            $defaults->configure(...$previous);
        }
    }

    /**
     * @param class-string<Model&TracksTranslations> $model
     *
     * @return Builder<Model&TracksTranslations>
     */
    private function query(string $model): Builder
    {
        /** @var Builder<Model&TracksTranslations> $query */
        $query = $model::query();
        $ids = array_values(array_filter((array) $this->option('id'), \is_string(...)));

        return $ids === [] ? $query : $query->whereKey($ids);
    }

    private function label(Model $record): string
    {
        $key = $record->getKey();

        return \sprintf('%s #%s', $record->getMorphClass(), \is_scalar($key) ? (string) $key : '?');
    }

    private static function attributeLabel(Model $record, AttributeOutcome $outcome): string
    {
        return $outcome->recordType === $record->getMorphClass() && (string) $outcome->recordKey === (\is_scalar($record->getKey()) ? (string) $record->getKey() : '')
            ? $outcome->attribute
            : \sprintf('%s #%s %s', $outcome->recordType, (string) $outcome->recordKey, $outcome->attribute);
    }

    private function configString(string $key, string $default): string
    {
        $value = config($key);

        return \is_string($value) ? $value : $default;
    }
}

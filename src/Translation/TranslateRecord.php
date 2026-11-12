<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Translation;

use HtmlTranslator\Exception\TranslatorException;
use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Enums\OverwritePolicy;
use HtmlTranslator\Filament\Exceptions\InvalidPluginConfigurationException;
use HtmlTranslator\Filament\Support\TranslationDefaults;
use HtmlTranslator\Filament\Support\TranslationLocales;
use HtmlTranslator\Issue;
use HtmlTranslator\Laravel\Contracts\TranslatesHtml;
use HtmlTranslator\Laravel\PendingTranslation;
use HtmlTranslator\TranslationStats;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Translates the source-locale values of a record (and of the relations asked for) into one target locale.
 *
 * Every translator call happens before the database transaction, so no transaction stays open during network
 * calls. Only complete results are written; incomplete and failed attributes keep their previous value and are
 * reported. The overwrite policy decides which existing translations may be replaced.
 */
final readonly class TranslateRecord
{
    public function __construct(
        private TranslatesHtml $translator,
        private TranslationStateStore $states,
        private TranslationLocales $locales,
        private TranslationDefaults $defaults,
        private ConnectionResolverInterface $database,
    ) {}

    /**
     * @param list<string> $relations relations of $record translated together with it (opt-in, never guessed)
     */
    public function __invoke(
        Model&TracksTranslations $record,
        string $locale,
        OverwritePolicy $policy = OverwritePolicy::OnlyMissing,
        array $relations = [],
    ): TranslationReport {
        if (! \in_array($locale, $this->locales->targets(), true)) {
            throw InvalidPluginConfigurationException::notATargetLocale($locale, $this->locales->targets());
        }

        $outcomes = [];
        $stats = [];
        /** @var list<array{model: Model&TracksTranslations, attribute: string, source: string, value: string}> $writes */
        $writes = [];

        foreach ([$record, ...$this->related($record, $relations)] as $model) {
            foreach ($model->getTranslatableAttributes() as $attribute) {
                [$outcome, $write, $stat] = $this->translateAttribute($model, $attribute, $locale, $policy);
                $outcomes[] = $outcome;
                if ($stat instanceof TranslationStats) {
                    $stats[] = $stat;
                }

                if ($write !== null) {
                    $writes[] = ['model' => $model, 'attribute' => $attribute, 'source' => $write[0], 'value' => $write[1]];
                }
            }
        }

        $this->write($writes, $locale);

        return new TranslationReport($outcomes, $stats);
    }

    /**
     * @return array{AttributeOutcome, array{string, string}|null, TranslationStats|null}
     */
    private function translateAttribute(
        Model&TracksTranslations $model,
        string $attribute,
        string $locale,
        OverwritePolicy $policy,
    ): array {
        $outcome = fn(OutcomeStatus $status, ?string $reason = null, array $issues = []): AttributeOutcome => new AttributeOutcome(
            $model->getMorphClass(),
            $this->key($model),
            $attribute,
            $status,
            $reason,
            array_values(array_filter($issues, \is_string(...))),
        );

        $source = $model->getTranslation($attribute, $this->locales->source(), false);
        if (! \is_string($source) || trim($source) === '') {
            return [$outcome(OutcomeStatus::Skipped, 'empty_source'), null, null];
        }

        $status = $this->states->status($model, $attribute, $locale);
        if (! $policy->allows($status)) {
            return [$outcome(OutcomeStatus::Skipped, 'kept_' . $status?->value), null, null];
        }

        $isHtml = \in_array($attribute, $model->getHtmlTranslatableAttributes(), true);

        try {
            $document = $this->pending()->translate($isHtml ? $source : $this->escape($source), $locale);
        } catch (TranslatorException $exception) {
            return [$outcome(OutcomeStatus::Failed, 'translator_error', [$exception->getMessage()]), null, null];
        }

        if (! $document->isComplete()) {
            $issues = array_map(static fn(Issue $issue): string => $issue->message, $document->issues());

            return [$outcome(OutcomeStatus::Incomplete, 'incomplete', $issues), null, $document->stats()];
        }

        $value = $isHtml ? $document->html() : $this->unescape($document->html());

        return [$outcome(OutcomeStatus::Translated), [$source, $value], $document->stats()];
    }

    /**
     * @param list<array{model: Model&TracksTranslations, attribute: string, source: string, value: string}> $writes
     */
    private function write(array $writes, string $locale): void
    {
        if ($writes === []) {
            return;
        }

        $this->database->connection()->transaction(function () use ($writes, $locale): void {
            $models = [];
            foreach ($writes as $write) {
                $write['model']->setTranslation($write['attribute'], $locale, $write['value']);
                $models[spl_object_id($write['model'])] = $write['model'];
            }

            foreach ($models as $model) {
                $model->save();
            }

            foreach ($writes as $write) {
                $this->states->markMachine(
                    $write['model'],
                    $write['attribute'],
                    $locale,
                    $write['source'],
                    $this->defaults->provider(),
                    $this->defaults->profile(),
                );
            }
        });
    }

    private function pending(): PendingTranslation
    {
        $pending = $this->translator->from($this->locales->source());
        $profile = $this->defaults->profile();
        if ($profile !== null) {
            $pending = $pending->profile($profile);
        }

        $provider = $this->defaults->provider();

        return $provider === null ? $pending : $pending->provider($provider);
    }

    /**
     * @param list<string> $relations
     *
     * @return list<Model&TracksTranslations>
     */
    private function related(Model $record, array $relations): array
    {
        $models = [];
        foreach ($relations as $name) {
            if (! $record->isRelation($name)) {
                throw new InvalidArgumentException(\sprintf('html-translator: "%s" is not a relation of %s.', $name, $record::class));
            }

            // Fresh from the database: a relation loaded earlier may be stale.
            $results = $record->unsetRelation($name)->getRelationValue($name);
            foreach ($results instanceof Collection ? $results->all() : [$results] as $related) {
                if ($related === null) {
                    continue;
                }

                if (! $related instanceof Model || ! $related instanceof TracksTranslations) {
                    throw new InvalidArgumentException(\sprintf(
                        'html-translator: the relation "%s" returns %s, which does not implement TracksTranslations.',
                        $name,
                        get_debug_type($related),
                    ));
                }

                $models[] = $related;
            }
        }

        return $models;
    }

    private function key(Model $model): int|string
    {
        $key = $model->getKey();

        return \is_int($key) || \is_string($key) ? $key : '';
    }

    /**
     * Plain text goes to the HTML translator as escaped text, so "<" or "&" in a title can never become markup.
     */
    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private function unescape(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

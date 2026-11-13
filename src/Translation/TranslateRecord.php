<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Translation;

use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Enums\OverwritePolicy;
use HtmlTranslator\Filament\Exceptions\InvalidPluginConfigurationException;
use HtmlTranslator\Filament\Support\RelatedRecords;
use HtmlTranslator\Filament\Support\TranslationDefaults;
use HtmlTranslator\Filament\Support\TranslationLocales;
use HtmlTranslator\TranslationStats;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;

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
        private TextTranslator $texts,
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
        $this->assertTarget($locale);

        $outcomes = [];
        $stats = [];
        /** @var list<array{model: Model&TracksTranslations, attribute: string, source: string, value: string}> $writes */
        $writes = [];

        foreach ([$record, ...RelatedRecords::of($record, $relations)] as $model) {
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
     * What a translation would do, without calling the translator or writing anything: one outcome per attribute,
     * `Translated` for the attributes that would be sent to the translator, `Skipped` (with the reason) for the rest.
     *
     * @param list<string> $relations
     *
     * @return list<AttributeOutcome>
     */
    public function preview(
        Model&TracksTranslations $record,
        string $locale,
        OverwritePolicy $policy = OverwritePolicy::OnlyMissing,
        array $relations = [],
    ): array {
        $this->assertTarget($locale);

        $outcomes = [];
        foreach ([$record, ...RelatedRecords::of($record, $relations)] as $model) {
            foreach ($model->getTranslatableAttributes() as $attribute) {
                $check = $this->check($model, $attribute, $locale, $policy);
                $outcomes[] = $check instanceof AttributeOutcome
                    ? $check
                    : $this->outcome($model, $attribute, OutcomeStatus::Translated);
            }
        }

        return $outcomes;
    }

    private function assertTarget(string $locale): void
    {
        if (! \in_array($locale, $this->locales->targets(), true)) {
            throw InvalidPluginConfigurationException::notATargetLocale($locale, $this->locales->targets());
        }
    }

    /**
     * The checks made before any translator call: the source text that would be sent, or why the attribute is
     * skipped.
     */
    private function check(Model&TracksTranslations $model, string $attribute, string $locale, OverwritePolicy $policy): string|AttributeOutcome
    {
        $source = $model->getTranslation($attribute, $this->locales->source(), false);
        if (! \is_string($source) || trim($source) === '') {
            return $this->outcome($model, $attribute, OutcomeStatus::Skipped, TranslationReport::EMPTY_SOURCE);
        }

        $status = $this->states->status($model, $attribute, $locale);
        if (! $policy->allows($status)) {
            return $this->outcome($model, $attribute, OutcomeStatus::Skipped, 'kept_' . $status?->value);
        }

        return $source;
    }

    /**
     * @param list<string> $issues
     */
    private function outcome(Model $model, string $attribute, OutcomeStatus $status, ?string $reason = null, array $issues = []): AttributeOutcome
    {
        return new AttributeOutcome($model->getMorphClass(), $this->key($model), $attribute, $status, $reason, $issues);
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
        $source = $this->check($model, $attribute, $locale, $policy);
        if ($source instanceof AttributeOutcome) {
            return [$source, null, null];
        }

        $outcome = fn(OutcomeStatus $status, ?string $reason = null, array $issues = []): AttributeOutcome => $this->outcome(
            $model,
            $attribute,
            $status,
            $reason,
            array_values(array_filter($issues, \is_string(...))),
        );

        $result = $this->texts->translate(
            $source,
            \in_array($attribute, $model->getHtmlTranslatableAttributes(), true),
            $locale,
        );

        return match ($result->status) {
            OutcomeStatus::Translated => [$outcome(OutcomeStatus::Translated), [$source, (string) $result->value], $result->stats],
            OutcomeStatus::Incomplete => [$outcome(OutcomeStatus::Incomplete, 'incomplete', $result->issues), null, $result->stats],
            default => [$outcome(OutcomeStatus::Failed, 'translator_error', $result->issues), null, null],
        };
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


    private function key(Model $model): int|string
    {
        $key = $model->getKey();

        return \is_int($key) || \is_string($key) ? $key : '';
    }


}

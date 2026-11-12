<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tables\Columns;

use Filament\Tables\Columns\TextColumn;
use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Enums\TranslationStatus;
use HtmlTranslator\Filament\Models\TranslationState;
use HtmlTranslator\Filament\Support\Lang;
use HtmlTranslator\Filament\Support\TranslationLocales;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * One badge per target locale summarising the record's translations (and those of the configured relations):
 * missing if anything is untranslated, else stale if a source changed, else machine if anything is unreviewed, else
 * reviewed. Eager-load `translationStates` on the table query to avoid one query per row.
 */
final class TranslationStatusColumn extends TextColumn
{
    private const string STALE = 'stale';

    /** @var list<string> */
    private array $relations = [];

    public static function getDefaultName(): string
    {
        return 'translation_status';
    }

    /**
     * @param list<string> $relations relations whose translations count towards the record's status
     */
    public function relations(array $relations): static
    {
        $this->relations = $relations;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(Lang::get('columns.status'))
            ->badge()
            ->state(fn(Model $record, TranslationStateStore $states, TranslationLocales $locales): array => $this->summary($record, $states, $locales))
            ->formatStateUsing(static fn(string $state): string => strtoupper(self::localeOf($state)) . ' · ' . Lang::get('status.' . self::statusOf($state)))
            ->color(static fn(string $state): string => match (self::statusOf($state)) {
                TranslationStatus::Machine->value => 'warning',
                TranslationStatus::Reviewed->value => 'success',
                self::STALE => 'danger',
                default => 'gray',
            });
    }

    private static function localeOf(string $state): string
    {
        $locale = strstr($state, ':', true);

        return $locale === false ? $state : $locale;
    }

    private static function statusOf(string $state): string
    {
        $status = strstr($state, ':');

        return $status === false ? '' : substr($status, 1);
    }

    /**
     * @return list<string> "locale:status" per target locale
     */
    private function summary(Model $record, TranslationStateStore $states, TranslationLocales $locales): array
    {
        if (! $record instanceof TracksTranslations) {
            return [];
        }

        $all = [];
        foreach ([$record, ...$this->related($record)] as $model) {
            $loaded = $model->relationLoaded('translationStates') ? $model->getRelation('translationStates') : null;
            $collection = $loaded instanceof Collection ? $loaded : $model->translationStates()->get();
            foreach ($collection as $state) {
                if ($state instanceof TranslationState) {
                    $all[] = $state;
                }
            }
        }

        $summary = [];
        foreach ($locales->targets() as $locale) {
            $summary[] = $locale . ':' . $this->aggregate(
                array_values(array_filter($all, static fn(TranslationState $state): bool => $state->locale === $locale)),
                $states,
            );
        }

        return $summary;
    }

    /**
     * @param list<TranslationState> $states
     */
    private function aggregate(array $states, TranslationStateStore $store): string
    {
        if ($states === []) {
            return TranslationStatus::Missing->value;
        }

        $statuses = array_map(static fn(TranslationState $state): TranslationStatus => $state->status, $states);
        if (\in_array(TranslationStatus::Missing, $statuses, true)) {
            return TranslationStatus::Missing->value;
        }

        foreach ($states as $state) {
            if ($store->isStale($state)) {
                return self::STALE;
            }
        }

        return \in_array(TranslationStatus::Machine, $statuses, true)
            ? TranslationStatus::Machine->value
            : TranslationStatus::Reviewed->value;
    }

    /**
     * @return list<Model&TracksTranslations>
     */
    private function related(Model $record): array
    {
        $models = [];
        foreach ($this->relations as $name) {
            if (! $record->isRelation($name)) {
                continue;
            }

            $results = $record->getRelationValue($name);
            foreach ($results instanceof Collection ? $results->all() : [$results] as $related) {
                if ($related instanceof Model && $related instanceof TracksTranslations) {
                    $models[] = $related;
                }
            }
        }

        return $models;
    }
}

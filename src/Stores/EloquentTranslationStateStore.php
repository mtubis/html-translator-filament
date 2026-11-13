<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Stores;

use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Enums\TranslationStatus;
use HtmlTranslator\Filament\Models\TranslationState;
use HtmlTranslator\Filament\Support\TranslationLocales;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final readonly class EloquentTranslationStateStore implements TranslationStateStore
{
    public function __construct(private TranslationLocales $locales) {}

    public function forRecord(Model&TracksTranslations $record): Collection
    {
        return $record->translationStates()->get();
    }

    public function ensureMissing(Model&TracksTranslations $record, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            foreach ($this->locales->targets() as $locale) {
                $record->translationStates()->firstOrCreate(
                    ['attribute' => $attribute, 'locale' => $locale],
                    ['status' => TranslationStatus::Missing],
                );
            }
        }
    }

    public function status(Model&TracksTranslations $record, string $attribute, string $locale): ?TranslationStatus
    {
        return $record->translationStates()
            ->where('attribute', $attribute)
            ->where('locale', $locale)
            ->first()
            ?->status;
    }

    public function markMachine(
        Model&TracksTranslations $record,
        string $attribute,
        string $locale,
        string $source,
        ?string $provider,
        ?string $profile,
    ): TranslationState {
        $state = $this->state($record, $attribute, $locale);
        $state->fill([
            'status' => TranslationStatus::Machine,
            'source_hash' => TranslationState::hashSource($source),
            'translated_at' => Carbon::now(),
            'provider' => $provider,
            'profile' => $profile,
        ])->save();

        return $state;
    }

    public function markReviewed(
        Model&TracksTranslations $record,
        string $attribute,
        string $locale,
        ?int $reviewerId,
    ): TranslationState {
        $state = $this->state($record, $attribute, $locale);
        $state->fill([
            'status' => TranslationStatus::Reviewed,
            'reviewed_at' => Carbon::now(),
            'reviewed_by' => $reviewerId,
        ])->save();

        return $state;
    }

    public function markReviewedAll(Model&TracksTranslations $record, string $locale, ?int $reviewerId): int
    {
        $count = 0;
        foreach ($record->translationStates()->where('locale', $locale)->get() as $state) {
            $changes = ['status' => TranslationStatus::Reviewed, 'reviewed_at' => Carbon::now(), 'reviewed_by' => $reviewerId];

            if ($state->status === TranslationStatus::Missing) {
                // A translation written by hand: reviewable once it has a value. It was made from the current source.
                $target = $record->getTranslation($state->attribute, $locale, false);
                if (! \is_string($target) || trim($target) === '') {
                    continue;
                }

                $source = $record->getTranslation($state->attribute, $this->locales->source(), false);
                $changes['source_hash'] = TranslationState::hashSource(\is_string($source) ? $source : null);
            } elseif ($state->status !== TranslationStatus::Machine) {
                continue;
            }

            $state->fill($changes)->save();
            ++$count;
        }

        return $count;
    }

    public function isStale(TranslationState $state): bool
    {
        return $state->isStale($this->locales->source());
    }

    public function deleteFor(string $morphType, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        TranslationState::query()
            ->where('translatable_type', $morphType)
            ->whereIn('translatable_id', $ids)
            ->delete();
    }

    private function state(Model&TracksTranslations $record, string $attribute, string $locale): TranslationState
    {
        return $record->translationStates()->firstOrNew(['attribute' => $attribute, 'locale' => $locale]);
    }
}

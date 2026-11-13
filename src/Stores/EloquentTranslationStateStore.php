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
        $state->fill($this->reviewedChanges($record, $attribute, $reviewerId))->save();

        return $state;
    }

    public function markReviewedAll(Model&TracksTranslations $record, string $locale, ?int $reviewerId, bool $includeStale = true): int
    {
        $states = $this->reviewable($record, $locale, $includeStale);
        foreach ($states as $state) {
            $state->fill($this->reviewedChanges($record, $state->attribute, $reviewerId))->save();
        }

        return \count($states);
    }

    public function reviewable(Model&TracksTranslations $record, string $locale, bool $includeStale = true): array
    {
        $reviewable = [];
        foreach ($record->translationStates()->where('locale', $locale)->orderBy('id')->get() as $state) {
            $current = $state->source_hash === $this->sourceHash($record, $state->attribute);

            $include = match ($state->status) {
                // A translation written by hand: reviewable once it has a value. It was made from the current source.
                TranslationStatus::Missing => $this->hasValue($record, $state->attribute, $locale),
                TranslationStatus::Machine => $current || $includeStale,
                // Reviewed and current: nothing to do. Reviewed but stale: accepted again for the new source.
                TranslationStatus::Reviewed => ! $current && $includeStale,
            };

            if ($include) {
                $reviewable[] = $state;
            }
        }

        return $reviewable;
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

    /**
     * Reviewing accepts the translation for the current source text, so the stored hash follows it.
     *
     * @return array<string, mixed>
     */
    private function reviewedChanges(Model&TracksTranslations $record, string $attribute, ?int $reviewerId): array
    {
        return [
            'status' => TranslationStatus::Reviewed,
            'reviewed_at' => Carbon::now(),
            'reviewed_by' => $reviewerId,
            'source_hash' => $this->sourceHash($record, $attribute),
        ];
    }

    private function sourceHash(Model&TracksTranslations $record, string $attribute): ?string
    {
        $source = $record->getTranslation($attribute, $this->locales->source(), false);

        return TranslationState::hashSource(\is_string($source) ? $source : null);
    }

    private function hasValue(Model&TracksTranslations $record, string $attribute, string $locale): bool
    {
        $value = $record->getTranslation($attribute, $locale, false);

        return \is_string($value) && trim($value) !== '';
    }

    private function state(Model&TracksTranslations $record, string $attribute, string $locale): TranslationState
    {
        return $record->translationStates()->firstOrNew(['attribute' => $attribute, 'locale' => $locale]);
    }
}

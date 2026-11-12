<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Contracts;

use HtmlTranslator\Filament\Models\TranslationState;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Reads and writes translation states. The Eloquent implementation is bound by default; an application can bind its
 * own (e.g. to keep states elsewhere) and every action, column and job of the plugin follows.
 */
interface TranslationStateStore
{
    /**
     * @return Collection<int, TranslationState>
     */
    public function forRecord(Model&TracksTranslations $record): Collection;

    /**
     * Creates a `missing` state for each attribute in each target locale that has none. Never changes existing states.
     *
     * @param list<string> $attributes
     */
    public function ensureMissing(Model&TracksTranslations $record, array $attributes): void;

    public function markMachine(
        Model&TracksTranslations $record,
        string $attribute,
        string $locale,
        string $source,
        ?string $provider,
        ?string $profile,
    ): TranslationState;

    public function markReviewed(
        Model&TracksTranslations $record,
        string $attribute,
        string $locale,
        ?int $reviewerId,
    ): TranslationState;

    /**
     * True when the source text changed after the translation was made. `missing` states are never stale.
     */
    public function isStale(TranslationState $state): bool;

    /**
     * For records removed without model events (e.g. a database cascade).
     *
     * @param list<int|string> $ids
     */
    public function deleteFor(string $morphType, array $ids): void;
}

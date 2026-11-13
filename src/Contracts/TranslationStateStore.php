<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Contracts;

use HtmlTranslator\Filament\Enums\TranslationStatus;
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

    /**
     * The status of one attribute in one locale; null when no state exists yet.
     */
    public function status(Model&TracksTranslations $record, string $attribute, string $locale): ?TranslationStatus;

    public function markMachine(
        Model&TracksTranslations $record,
        string $attribute,
        string $locale,
        string $source,
        ?string $provider,
        ?string $profile,
    ): TranslationState;

    /**
     * Marks one translation as reviewed and accepts it for the current source text (it is no longer stale).
     */
    public function markReviewed(
        Model&TracksTranslations $record,
        string $attribute,
        string $locale,
        ?int $reviewerId,
    ): TranslationState;

    /**
     * Marks the record's translations in the locale as reviewed: the states listed by reviewable(). Reviewing accepts
     * a translation for the current source text, so stale ones are no longer stale afterwards.
     *
     * @return int the number of states marked
     */
    public function markReviewedAll(Model&TracksTranslations $record, string $locale, ?int $reviewerId, bool $includeStale = true): int;

    /**
     * The states markReviewedAll() would mark: machine drafts, translations written by hand (a `missing` state whose
     * target value is not empty), and reviewed translations whose source changed since. With `$includeStale` false,
     * stale machine drafts and stale reviewed translations are left out. Empty translations are never reviewable.
     *
     * @return list<TranslationState>
     */
    public function reviewable(Model&TracksTranslations $record, string $locale, bool $includeStale = true): array;

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

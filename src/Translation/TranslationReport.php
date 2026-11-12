<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Translation;

use HtmlTranslator\TranslationStats;

/**
 * Result of TranslateRecord: one outcome per attribute plus the summed statistics of every translator call. Safe to
 * show to editors: it contains no prompt text, model answers or keys.
 */
final readonly class TranslationReport
{
    /**
     * @param list<AttributeOutcome> $outcomes
     * @param list<TranslationStats> $stats
     */
    public function __construct(
        private array $outcomes,
        private array $stats = [],
    ) {}

    /**
     * @return list<AttributeOutcome>
     */
    public function outcomes(): array
    {
        return $this->outcomes;
    }

    public function translated(): int
    {
        return $this->count(OutcomeStatus::Translated);
    }

    public function skipped(): int
    {
        return $this->count(OutcomeStatus::Skipped);
    }

    public function incomplete(): int
    {
        return $this->count(OutcomeStatus::Incomplete);
    }

    public function failed(): int
    {
        return $this->count(OutcomeStatus::Failed);
    }

    /**
     * Token counts stay null while no call reported them.
     *
     * @return array{units: int, batches: int, input_tokens: int|null, output_tokens: int|null, cache_hits: int}
     */
    public function stats(): array
    {
        $sum = ['units' => 0, 'batches' => 0, 'input_tokens' => null, 'output_tokens' => null, 'cache_hits' => 0];
        foreach ($this->stats as $stats) {
            $sum['units'] += $stats->units;
            $sum['batches'] += $stats->batches;
            $sum['cache_hits'] += $stats->cacheHits;
            if ($stats->inputTokens !== null) {
                $sum['input_tokens'] = ($sum['input_tokens'] ?? 0) + $stats->inputTokens;
            }

            if ($stats->outputTokens !== null) {
                $sum['output_tokens'] = ($sum['output_tokens'] ?? 0) + $stats->outputTokens;
            }
        }

        return $sum;
    }

    private function count(OutcomeStatus $status): int
    {
        return \count(array_filter($this->outcomes, static fn(AttributeOutcome $outcome): bool => $outcome->status === $status));
    }
}

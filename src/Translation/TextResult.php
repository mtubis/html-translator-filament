<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Translation;

use HtmlTranslator\TranslationStats;

/**
 * Result of translating one text. Only complete results carry a value to write.
 */
final readonly class TextResult
{
    /**
     * @param list<string> $issues
     */
    private function __construct(
        public OutcomeStatus $status,
        public ?string $value,
        public array $issues,
        public ?TranslationStats $stats,
    ) {}

    public static function translated(string $value, TranslationStats $stats): self
    {
        return new self(OutcomeStatus::Translated, $value, [], $stats);
    }

    /**
     * @param list<string> $issues
     */
    public static function incomplete(array $issues, TranslationStats $stats): self
    {
        return new self(OutcomeStatus::Incomplete, null, $issues, $stats);
    }

    public static function failed(string $message): self
    {
        return new self(OutcomeStatus::Failed, null, [$message], null);
    }

    public function isTranslated(): bool
    {
        return $this->status === OutcomeStatus::Translated;
    }
}

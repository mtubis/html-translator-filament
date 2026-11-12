<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Translation;

/**
 * What happened to one attribute of one record in a translation run.
 */
final readonly class AttributeOutcome
{
    /**
     * @param list<string> $issues messages from the translator (never prompt text or keys)
     */
    public function __construct(
        public string $recordType,
        public int|string $recordKey,
        public string $attribute,
        public OutcomeStatus $status,
        public ?string $reason = null,
        public array $issues = [],
    ) {}
}

<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Enums;

/**
 * Which existing translations a run may replace. Only Force touches reviewed translations, and callers must choose
 * it explicitly (a reviewed text is a human's work).
 */
enum OverwritePolicy: string
{
    case OnlyMissing = 'only_missing';
    case OverwriteMachine = 'overwrite_machine';
    case Force = 'force';

    public function allows(?TranslationStatus $status): bool
    {
        return match ($status) {
            null, TranslationStatus::Missing => true,
            TranslationStatus::Machine => $this !== self::OnlyMissing,
            TranslationStatus::Reviewed => $this === self::Force,
        };
    }
}

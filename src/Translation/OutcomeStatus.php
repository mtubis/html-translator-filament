<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Translation;

enum OutcomeStatus: string
{
    /** Written to the target locale and marked `machine`. */
    case Translated = 'translated';

    /** Not sent: empty source, or the overwrite policy keeps the existing translation. */
    case Skipped = 'skipped';

    /** Sent, but some units kept their source text: nothing written, the issues explain why. */
    case Incomplete = 'incomplete';

    /** The translator threw; nothing written. */
    case Failed = 'failed';
}

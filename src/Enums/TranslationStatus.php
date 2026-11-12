<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use HtmlTranslator\Filament\HtmlTranslatorFilamentServiceProvider;

/**
 * Where one translated attribute stands. Being out of date is not a status: it is derived from the source hash, so a
 * reviewed translation stays reviewed when its source changes and is reported as stale instead.
 */
enum TranslationStatus: string implements HasColor, HasLabel
{
    case Missing = 'missing';
    case Machine = 'machine';
    case Reviewed = 'reviewed';

    public function getLabel(): string
    {
        $key = HtmlTranslatorFilamentServiceProvider::TRANSLATION_NAMESPACE . '::messages.status.' . $this->value;
        $label = __($key);

        return \is_string($label) ? $label : $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Missing => 'gray',
            self::Machine => 'warning',
            self::Reviewed => 'success',
        };
    }
}

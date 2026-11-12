<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament;

use Illuminate\Support\ServiceProvider;

final class HtmlTranslatorFilamentServiceProvider extends ServiceProvider
{
    public const string TRANSLATION_NAMESPACE = 'html-translator-filament';

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', self::TRANSLATION_NAMESPACE);
    }
}

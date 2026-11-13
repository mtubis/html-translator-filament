<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Support;

use HtmlTranslator\Filament\HtmlTranslatorFilamentServiceProvider;

/**
 * The plugin's UI strings (lang/{locale}/messages.php), namespaced so applications can override them in
 * lang/vendor/html-translator-filament.
 */
final class Lang
{
    /**
     * @param array<string, int|string> $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        $text = __(HtmlTranslatorFilamentServiceProvider::TRANSLATION_NAMESPACE . '::messages.' . $key, $replace);

        return \is_string($text) ? $text : $key;
    }

    /**
     * A text with plural forms ("one|other", or three forms in Polish), chosen by the locale's rules; `:count` is
     * replaced by the number.
     *
     * @param array<string, int|string> $replace
     */
    public static function choice(string $key, int $count, array $replace = []): string
    {
        return trans_choice(HtmlTranslatorFilamentServiceProvider::TRANSLATION_NAMESPACE . '::messages.' . $key, $count, $replace);
    }
}

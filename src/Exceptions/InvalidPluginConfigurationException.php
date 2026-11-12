<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Exceptions;

use HtmlTranslator\Exception\TranslatorException;
use InvalidArgumentException;

/**
 * The panel plugin was configured inconsistently. Like every exception of the html-translator packages it
 * implements TranslatorException, so one catch covers the whole stack.
 */
final class InvalidPluginConfigurationException extends InvalidArgumentException implements TranslatorException
{
    public static function missingLocales(): self
    {
        return new self('html-translator: call locales() with the source and target locales.');
    }

    /**
     * @param list<string> $locales
     */
    public static function sourceNotInLocales(string $source, array $locales): self
    {
        return new self(\sprintf(
            'html-translator: the source locale "%s" is not one of the locales (%s).',
            $source,
            implode(', ', $locales),
        ));
    }

    public static function invalidLocale(string $locale): self
    {
        return new self(\sprintf('html-translator: "%s" is not a valid locale; use codes such as "es" or "pt-BR".', $locale));
    }

    /**
     * @param list<string> $targets
     * @param list<string> $otherTargets
     */
    public static function panelsDisagree(string $source, array $targets, string $otherSource, array $otherTargets): self
    {
        return new self(\sprintf(
            'html-translator: every panel must use the same locales (%s → %s, got %s → %s).',
            $source,
            implode(', ', $targets),
            $otherSource,
            implode(', ', $otherTargets),
        ));
    }

    public static function notRegistered(): self
    {
        return new self('html-translator: register HtmlTranslatorPlugin on a Filament panel before translating.');
    }

    public static function duplicateLocale(string $locale): self
    {
        return new self(\sprintf('html-translator: the locale "%s" is listed twice.', $locale));
    }
}

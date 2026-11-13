<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Translation;

use HtmlTranslator\Exception\TranslatorException;
use HtmlTranslator\Filament\Support\TranslationDefaults;
use HtmlTranslator\Filament\Support\TranslationLocales;
use HtmlTranslator\Issue;
use HtmlTranslator\Laravel\Contracts\TranslatesHtml;
use HtmlTranslator\Laravel\PendingTranslation;

/**
 * Translates one source-locale text into a target locale with the plugin's profile and provider. HTML is sent as
 * it is; plain text is escaped for the HTML translator and decoded again, so "<" or "&" can never become markup.
 */
final readonly class TextTranslator
{
    public function __construct(
        private TranslatesHtml $translator,
        private TranslationLocales $locales,
        private TranslationDefaults $defaults,
    ) {}

    public function translate(string $source, bool $isHtml, string $locale): TextResult
    {
        try {
            $document = $this->pending()->translate($isHtml ? $source : $this->escape($source), $locale);
        } catch (TranslatorException $exception) {
            return TextResult::failed($exception->getMessage());
        }

        if (! $document->isComplete()) {
            return TextResult::incomplete(
                array_map(static fn(Issue $issue): string => $issue->message, $document->issues()),
                $document->stats(),
            );
        }

        return TextResult::translated(
            $isHtml ? $document->html() : $this->unescape($document->html()),
            $document->stats(),
        );
    }

    private function pending(): PendingTranslation
    {
        $pending = $this->translator->from($this->locales->source());
        $profile = $this->defaults->profile();
        if ($profile !== null) {
            $pending = $pending->profile($profile);
        }

        $provider = $this->defaults->provider();

        return $provider === null ? $pending : $pending->provider($provider);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private function unescape(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

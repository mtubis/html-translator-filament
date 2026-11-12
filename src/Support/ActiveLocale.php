<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Support;

/**
 * Reads the locale being edited from a Livewire page or relation manager. Pages of lara-zeus/spatie-translatable
 * (and any component with a public `activeLocale` property) are supported without depending on that package.
 */
final readonly class ActiveLocale
{
    public function __construct(private TranslationLocales $locales) {}

    /**
     * The edited locale when it is a target locale, else null.
     */
    public function target(mixed $livewire): ?string
    {
        $locale = $this->edited($livewire);

        return $locale !== null && \in_array($locale, $this->locales->targets(), true) ? $locale : null;
    }

    /**
     * The edited locale if it is a target, else the first target locale.
     */
    public function targetOrDefault(mixed $livewire): ?string
    {
        return $this->target($livewire) ?? ($this->locales->targets()[0] ?? null);
    }

    /**
     * Whether the component tells which locale it edits (has a public `activeLocale`).
     */
    public function edits(mixed $livewire): bool
    {
        return \is_object($livewire) && property_exists($livewire, 'activeLocale');
    }

    public function isEditingSource(mixed $livewire): bool
    {
        return $this->locales->isConfigured() && $this->edited($livewire) === $this->locales->source();
    }

    private function edited(mixed $livewire): ?string
    {
        if (! \is_object($livewire) || ! property_exists($livewire, 'activeLocale')) {
            return null;
        }

        $locale = $livewire->activeLocale;

        return \is_string($locale) && $locale !== '' ? $locale : null;
    }
}

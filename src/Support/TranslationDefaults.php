<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Support;

/**
 * The profile and provider names configured on the plugin (null: the html-translator-laravel defaults). Published by
 * HtmlTranslatorPlugin::register() so jobs and services use them outside panel requests too.
 */
final class TranslationDefaults
{
    private ?string $profile = null;

    private ?string $provider = null;

    public function configure(?string $profile, ?string $provider): void
    {
        $this->profile = $profile;
        $this->provider = $provider;
    }

    public function profile(): ?string
    {
        return $this->profile;
    }

    public function provider(): ?string
    {
        return $this->provider;
    }
}

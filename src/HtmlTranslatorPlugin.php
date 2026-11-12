<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use HtmlTranslator\Filament\Exceptions\InvalidPluginConfigurationException;
use LogicException;

/**
 * Panel-level configuration shared by every action, column and job of the plugin:
 *
 *     $panel->plugin(HtmlTranslatorPlugin::make()
 *         ->sourceLocale('es')
 *         ->locales(['es', 'en'])
 *         ->profile('site')      // an entry of config/html-translator.php; null = the package default
 *         ->provider(null)       // likewise
 *         ->queue());            // translate in jobs instead of the request
 */
final class HtmlTranslatorPlugin implements Plugin
{
    public const string ID = 'html-translator';

    private ?string $sourceLocale = null;

    /** @var list<string> */
    private array $locales = [];

    private ?string $profile = null;

    private ?string $provider = null;

    private bool $queue = false;

    public static function make(): self
    {
        return new self();
    }

    /**
     * The plugin registered on the current panel.
     */
    public static function get(): self
    {
        $plugin = \filament(self::ID);

        if (! $plugin instanceof self) {
            throw new LogicException('html-translator: the plugin is not registered on the current Filament panel.');
        }

        return $plugin;
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}

    public function sourceLocale(string $locale): self
    {
        $this->sourceLocale = $this->validLocale($locale);

        return $this;
    }

    /**
     * @param list<string> $locales the source locale and every target locale
     */
    public function locales(array $locales): self
    {
        $valid = [];
        foreach ($locales as $locale) {
            $locale = $this->validLocale($locale);
            if (\in_array($locale, $valid, true)) {
                throw InvalidPluginConfigurationException::duplicateLocale($locale);
            }

            $valid[] = $locale;
        }

        $this->locales = $valid;

        return $this;
    }

    public function profile(?string $profile): self
    {
        $this->profile = $profile;

        return $this;
    }

    public function provider(?string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function queue(bool $queue = true): self
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Defaults to the first locale, so `locales(['es', 'en'])` alone is a complete configuration.
     */
    public function getSourceLocale(): string
    {
        if ($this->locales === []) {
            throw InvalidPluginConfigurationException::missingLocales();
        }

        $source = $this->sourceLocale ?? $this->locales[0];
        if (! \in_array($source, $this->locales, true)) {
            throw InvalidPluginConfigurationException::sourceNotInLocales($source, $this->locales);
        }

        return $source;
    }

    /**
     * @return list<string>
     */
    public function getLocales(): array
    {
        return $this->locales;
    }

    /**
     * @return list<string>
     */
    public function getTargetLocales(): array
    {
        $source = $this->getSourceLocale();

        return \array_values(\array_filter($this->locales, static fn(string $locale): bool => $locale !== $source));
    }

    public function getProfile(): ?string
    {
        return $this->profile;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function shouldQueue(): bool
    {
        return $this->queue;
    }

    private function validLocale(string $locale): string
    {
        $trimmed = \trim($locale);
        if ($trimmed === '' || $trimmed !== $locale) {
            throw InvalidPluginConfigurationException::invalidLocale($locale);
        }

        return $locale;
    }
}

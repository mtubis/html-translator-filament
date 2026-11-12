<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use HtmlTranslator\Filament\Exceptions\InvalidPluginConfigurationException;
use HtmlTranslator\Filament\Support\TranslationDefaults;
use HtmlTranslator\Filament\Support\TranslationLocales;
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

    private ?string $queueConnection = null;

    private ?string $queueName = null;

    private ?int $rateLimit = null;

    public static function make(): self
    {
        return new self();
    }

    /**
     * The plugin registered on the current panel.
     */
    public static function get(): self
    {
        $plugin = filament(self::ID);

        if (! $plugin instanceof self) {
            throw new LogicException('html-translator: the plugin is not registered on the current Filament panel.');
        }

        return $plugin;
    }

    public function getId(): string
    {
        return self::ID;
    }

    /**
     * Publishes the locales to the container, so models, observers and jobs know them outside panel requests too.
     * Filament calls this as soon as the plugin is added to a panel.
     */
    public function register(Panel $panel): void
    {
        app(TranslationLocales::class)->configure($this->getSourceLocale(), $this->getTargetLocales());
        app(TranslationDefaults::class)->configure(
            $this->profile,
            $this->provider,
            $this->queue,
            $this->queueConnection,
            $this->queueName,
            $this->rateLimit,
        );
    }

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

    /**
     * Translate in queued jobs; the editor gets a database notification when they finish (enable
     * `->databaseNotifications()` on the panel and run a queue worker).
     */
    public function queue(bool $queue = true, ?string $connection = null, ?string $queueName = null): self
    {
        $this->queue = $queue;
        $this->queueConnection = $connection;
        $this->queueName = $queueName;

        return $this;
    }

    /**
     * At most this many translation jobs per minute, to stay under the provider's limits.
     */
    public function rateLimit(?int $jobsPerMinute): self
    {
        $this->rateLimit = $jobsPerMinute;

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

        return array_values(array_filter($this->locales, static fn(string $locale): bool => $locale !== $source));
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
        $trimmed = trim($locale);
        if ($trimmed === '' || $trimmed !== $locale) {
            throw InvalidPluginConfigurationException::invalidLocale($locale);
        }

        return $locale;
    }
}

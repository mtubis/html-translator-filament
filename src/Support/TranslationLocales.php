<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Support;

use HtmlTranslator\Filament\Exceptions\InvalidPluginConfigurationException;

/**
 * The source and target locales, published by HtmlTranslatorPlugin when a panel registers it. Models, observers and
 * jobs read them here, so they work outside a panel request too (console, seeders, queue workers).
 */
final class TranslationLocales
{
    private ?string $source = null;

    /** @var list<string> */
    private array $targets = [];

    /**
     * @param list<string> $targets
     */
    public function configure(string $source, array $targets): void
    {
        if ($this->source !== null && ($this->source !== $source || $this->targets !== $targets)) {
            throw InvalidPluginConfigurationException::panelsDisagree($this->source, $this->targets, $source, $targets);
        }

        $this->source = $source;
        $this->targets = $targets;
    }

    public function isConfigured(): bool
    {
        return $this->source !== null;
    }

    public function source(): string
    {
        return $this->source ?? throw InvalidPluginConfigurationException::notRegistered();
    }

    /**
     * @return list<string>
     */
    public function targets(): array
    {
        return $this->targets;
    }
}

<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Support;

/**
 * How the plugin translates, as configured on the panel: profile and provider names (null: the
 * html-translator-laravel defaults), whether to queue and where, and an optional rate limit. Published by
 * HtmlTranslatorPlugin::register() so jobs and services see it outside panel requests too.
 */
final class TranslationDefaults
{
    public const string RATE_LIMITER = 'html-translator';

    private ?string $profile = null;

    private ?string $provider = null;

    private bool $queue = false;

    private ?string $connection = null;

    private ?string $queueName = null;

    private ?int $rateLimit = null;

    public function configure(
        ?string $profile,
        ?string $provider,
        bool $queue = false,
        ?string $connection = null,
        ?string $queueName = null,
        ?int $rateLimit = null,
    ): void {
        $this->profile = $profile;
        $this->provider = $provider;
        $this->queue = $queue;
        $this->connection = $connection;
        $this->queueName = $queueName;
        $this->rateLimit = $rateLimit;
    }

    public function profile(): ?string
    {
        return $this->profile;
    }

    public function provider(): ?string
    {
        return $this->provider;
    }

    public function queue(): bool
    {
        return $this->queue;
    }

    public function connection(): ?string
    {
        return $this->connection;
    }

    public function queueName(): ?string
    {
        return $this->queueName;
    }

    /**
     * Translation jobs per minute, or null for no limit.
     */
    public function rateLimit(): ?int
    {
        return $this->rateLimit;
    }
}

<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament;

use Filament\Forms\Components\Field;
use HtmlTranslator\Filament\Actions\TranslateFieldAction;
use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Stores\EloquentTranslationStateStore;
use HtmlTranslator\Filament\Support\TranslationDefaults;
use HtmlTranslator\Filament\Support\TranslationLocales;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class HtmlTranslatorFilamentServiceProvider extends ServiceProvider
{
    public const string TRANSLATION_NAMESPACE = 'html-translator-filament';

    public const string MIGRATIONS_TAG = 'html-translator-filament-migrations';

    public function register(): void
    {
        $this->app->singleton(TranslationLocales::class);
        $this->app->singleton(TranslationDefaults::class);
        // Bound with singletonIf so an application's own store, registered earlier, wins.
        $this->app->singletonIf(TranslationStateStore::class, EloquentTranslationStateStore::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', self::TRANSLATION_NAMESPACE);

        // `->translateWithAi()` on any form field; `->hintAction(TranslateFieldAction::make())` is the explicit form.
        // A regular closure, not an arrow function: Macroable rebinds $this to the field.
        // A regular closure, not an arrow function: Macroable rebinds $this to the field (rector.php skips this file).
        Field::macro('translateWithAi', function (): mixed {
            return TranslateFieldAction::attachTo($this);
        });

        RateLimiter::for(TranslationDefaults::RATE_LIMITER, function (): Limit {
            $perMinute = $this->app->make(TranslationDefaults::class)->rateLimit();

            return $perMinute === null ? Limit::none() : Limit::perMinute($perMinute);
        });

        // Published, not loaded: the application decides when the table is created (and keeps an existing one).
        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
        ], self::MIGRATIONS_TAG);
    }
}

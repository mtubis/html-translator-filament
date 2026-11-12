<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Models\Concerns;

use HtmlTranslator\Filament\Models\TranslationState;
use HtmlTranslator\Filament\Observers\TranslationStateObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Tracks the translation states of a spatie-translatable model. Use it in a model that implements
 * `HtmlTranslator\Filament\Contracts\TracksTranslations`.
 *
 * @phpstan-require-extends Model
 */
trait HasTranslationStates
{
    public static function bootHasTranslationStates(): void
    {
        // Observers may not be attached while the model is still booting (Laravel 13).
        static::whenBooted(static fn() => static::observe(TranslationStateObserver::class));
    }

    /**
     * @return MorphMany<TranslationState, $this>
     */
    public function translationStates(): MorphMany
    {
        return $this->morphMany(TranslationState::class, 'translatable');
    }

    public function translationState(string $attribute, string $locale): ?TranslationState
    {
        return $this->translationStates()
            ->where('attribute', $attribute)
            ->where('locale', $locale)
            ->first();
    }
}

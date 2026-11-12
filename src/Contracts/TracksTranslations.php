<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Contracts;

use HtmlTranslator\Filament\Models\TranslationState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A translatable model whose translations the plugin tracks. Implement it with two traits:
 *
 *     final class Post extends Model implements TracksTranslations
 *     {
 *         use HasTranslations;          // spatie/laravel-translatable
 *         use HasTranslationStates;     // this package
 *     }
 */
interface TracksTranslations
{
    public function getTranslation(string $key, string $locale, bool $useFallbackLocale = true): mixed;

    /**
     * @return array<int, string>
     */
    public function getTranslatableAttributes(): array;

    /**
     * @return MorphMany<TranslationState, covariant Model>
     */
    public function translationStates(): MorphMany;
}

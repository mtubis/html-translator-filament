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
     * @param mixed $value
     */
    public function setTranslation(string $key, string $locale, $value): self;

    /**
     * @return array<int, string>
     */
    public function getTranslatableAttributes(): array;

    /**
     * Translatable attributes that hold HTML; the others are sent to the translator as plain text.
     *
     * @return list<string>
     */
    public function getHtmlTranslatableAttributes(): array;

    /**
     * @return MorphMany<TranslationState, covariant Model>
     */
    public function translationStates(): MorphMany;
}

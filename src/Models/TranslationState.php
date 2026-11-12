<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Models;

use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Enums\TranslationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Translation progress of one attribute of one record in one target locale.
 *
 * @property int $id
 * @property string $translatable_type
 * @property int|string $translatable_id
 * @property string $attribute
 * @property string $locale
 * @property TranslationStatus $status
 * @property string|null $source_hash
 * @property Carbon|null $translated_at
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property string|null $provider
 * @property string|null $profile
 */
final class TranslationState extends Model
{
    protected $table = 'translation_states';

    protected $fillable = [
        'attribute', 'locale', 'status', 'source_hash', 'translated_at', 'reviewed_at', 'reviewed_by', 'provider',
        'profile',
    ];

    protected $attributes = [
        'status' => 'missing',
    ];

    public static function hashSource(?string $source): ?string
    {
        return $source === null || $source === '' ? null : hash('sha256', $source);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isStale(string $sourceLocale): bool
    {
        if ($this->status === TranslationStatus::Missing) {
            return false;
        }

        $record = $this->translatable;
        if (! $record instanceof TracksTranslations) {
            return false;
        }

        $source = $record->getTranslation($this->attribute, $sourceLocale, false);

        return $this->source_hash !== self::hashSource(\is_string($source) ? $source : null);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TranslationStatus::class,
            'translated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'reviewed_by' => 'integer',
        ];
    }
}

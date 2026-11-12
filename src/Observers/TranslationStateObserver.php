<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Observers;

use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Support\TranslationLocales;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates `missing` states when a source text appears. Existing states are never changed here: staleness is derived
 * from the source hash, so a reviewed translation is never downgraded by an edit of the source.
 */
final readonly class TranslationStateObserver
{
    public function __construct(
        private TranslationStateStore $store,
        private TranslationLocales $locales,
    ) {}

    public function saved(Model $model): void
    {
        if (! $model instanceof TracksTranslations || ! $this->locales->isConfigured()) {
            return;
        }

        $source = $this->locales->source();
        $attributes = [];
        foreach ($model->getTranslatableAttributes() as $attribute) {
            if (! $model->wasRecentlyCreated && ! $model->wasChanged($attribute)) {
                continue;
            }

            $value = $model->getTranslation($attribute, $source, false);
            if (\is_string($value) && $value !== '') {
                $attributes[] = $attribute;
            }
        }

        if ($attributes !== []) {
            $this->store->ensureMissing($model, $attributes);
        }
    }

    public function deleted(Model $model): void
    {
        $key = $model->getKey();
        if ($model instanceof TracksTranslations && (\is_int($key) || \is_string($key))) {
            $this->store->deleteFor($model->getMorphClass(), [$key]);
        }
    }
}

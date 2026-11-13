<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Observers;

use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Models\TranslationState;
use HtmlTranslator\Filament\Support\PendingFieldTranslations;
use HtmlTranslator\Filament\Support\TranslationDefaults;
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
        private PendingFieldTranslations $pendingFields,
        private TranslationDefaults $defaults,
    ) {}

    public function saved(Model $model): void
    {
        if (! $model instanceof TracksTranslations || ! $this->locales->isConfigured()) {
            return;
        }

        $this->markFilledFields($model);

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

    /**
     * Fields filled by the field action become `machine` when the form is saved, unless their source changed in
     * the meantime (the draft would belong to an older text).
     */
    private function markFilledFields(Model&TracksTranslations $model): void
    {
        $pending = $this->pendingFields->forRecord($model);
        if ($pending === []) {
            return;
        }

        $this->pendingFields->forget($model);
        foreach ($pending as $field => $hash) {
            $attribute = strstr($field, '|', true);
            $locale = substr((string) strstr($field, '|'), 1);
            if ($attribute === false || $locale === '') {
                continue;
            }

            $source = $model->getTranslation($attribute, $this->locales->source(), false);
            if (\is_string($source) && TranslationState::hashSource($source) === $hash) {
                $this->store->markMachine($model, $attribute, $locale, $source, $this->defaults->provider(), $this->defaults->profile());
            }
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

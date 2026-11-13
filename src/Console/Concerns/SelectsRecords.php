<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Console\Concerns;

use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Exceptions\InvalidPluginConfigurationException;
use HtmlTranslator\Filament\Support\TranslationLocales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * Input shared by the plugin's commands: the `models*` argument (class names or morph aliases of models with
 * translation states), `--locale=*` (target locales of the plugin) and `--id=*`.
 */
trait SelectsRecords
{
    /**
     * @return list<class-string<Model&TracksTranslations>>
     */
    protected function models(): array
    {
        $models = [];
        foreach ((array) $this->argument('models') as $name) {
            if (! \is_string($name)) {
                continue;
            }

            $class = Relation::getMorphedModel($name) ?? $name;
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                throw new InvalidArgumentException(\sprintf('"%s" is neither an Eloquent model class nor a morph alias.', $name));
            }

            if (! is_subclass_of($class, TracksTranslations::class)) {
                throw new InvalidArgumentException(\sprintf('%s does not implement %s.', $class, TracksTranslations::class));
            }

            /** @var class-string<Model&TracksTranslations> $class */
            $models[] = $class;
        }

        return $models;
    }

    /**
     * @return list<string>
     */
    protected function targets(TranslationLocales $locales): array
    {
        if (! $locales->isConfigured()) {
            throw new InvalidArgumentException('The html-translator plugin is not registered on a Filament panel, so it has no locales.');
        }

        $requested = array_values(array_filter((array) $this->option('locale'), \is_string(...)));
        foreach ($requested as $locale) {
            if (! \in_array($locale, $locales->targets(), true)) {
                throw new InvalidArgumentException(InvalidPluginConfigurationException::notATargetLocale($locale, $locales->targets())->getMessage());
            }
        }

        return $requested === [] ? $locales->targets() : array_values(array_unique($requested));
    }

    /**
     * @param class-string<Model&TracksTranslations> $model
     *
     * @return Builder<Model&TracksTranslations>
     */
    protected function query(string $model): Builder
    {
        /** @var Builder<Model&TracksTranslations> $query */
        $query = $model::query();
        $ids = array_values(array_filter((array) $this->option('id'), \is_string(...)));

        return $ids === [] ? $query : $query->whereKey($ids);
    }

    protected function label(Model $record): string
    {
        $key = $record->getKey();

        return \sprintf('%s #%s', $record->getMorphClass(), \is_scalar($key) ? (string) $key : '?');
    }
}

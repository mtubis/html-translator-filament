<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Actions\Concerns;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use HtmlTranslator\Filament\Enums\OverwritePolicy;
use HtmlTranslator\Filament\Support\ActiveLocale;
use HtmlTranslator\Filament\Support\Lang;
use HtmlTranslator\Filament\Support\TranslationLocales;
use Illuminate\Contracts\Auth\Factory;

/**
 * The modal form shared by the record and bulk translate actions: target locale, overwrite policy and, for Force, an
 * explicit confirmation (a reviewed translation is a human's work).
 */
trait HasTranslationForm
{
    /**
     * @return list<Component>
     */
    protected function translationFormSchema(): array
    {
        return [
            Select::make('locale')
                ->label(Lang::get('actions.translate.locale'))
                ->options(static function (TranslationLocales $locales): array {
                    $targets = $locales->targets();

                    return array_combine($targets, array_map(strtoupper(...), $targets));
                })
                ->default(static fn(mixed $livewire, ActiveLocale $active): ?string => $active->targetOrDefault($livewire))
                ->disabled(static fn(TranslationLocales $locales): bool => \count($locales->targets()) === 1)
                ->dehydrated()
                ->required(),
            Radio::make('policy')
                ->label(Lang::get('actions.translate.policy'))
                ->options(array_combine(
                    array_map(static fn(OverwritePolicy $policy): string => $policy->value, OverwritePolicy::cases()),
                    array_map(static fn(OverwritePolicy $policy): string => Lang::get('actions.translate.policies.' . $policy->value), OverwritePolicy::cases()),
                ))
                ->default(OverwritePolicy::OnlyMissing->value)
                ->live()
                ->required(),
            Checkbox::make('confirm_force')
                ->label(Lang::get('actions.translate.confirm_force'))
                ->accepted()
                ->visible(static fn(Get $get): bool => $get('policy') === OverwritePolicy::Force->value),
        ];
    }

    /**
     * @param array<mixed> $data
     */
    protected static function policyFrom(array $data): OverwritePolicy
    {
        $policy = $data['policy'] ?? null;

        return \is_string($policy) ? OverwritePolicy::from($policy) : OverwritePolicy::OnlyMissing;
    }

    protected static function userId(Factory $auth): ?int
    {
        $id = $auth->guard()->id();

        return \is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    /**
     * @param array<mixed> $data
     */
    protected static function localeFrom(array $data, mixed $livewire, ActiveLocale $active): ?string
    {
        $locale = $data['locale'] ?? null;

        return \is_string($locale) && $locale !== '' ? $locale : $active->targetOrDefault($livewire);
    }
}

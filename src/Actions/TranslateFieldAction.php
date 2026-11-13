<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Support\ActiveLocale;
use HtmlTranslator\Filament\Support\Lang;
use HtmlTranslator\Filament\Support\PendingFieldTranslations;
use HtmlTranslator\Filament\Support\TranslationLocales;
use HtmlTranslator\Filament\Translation\TextTranslator;
use Illuminate\Database\Eloquent\Model;

/**
 * Hint action on a translatable form field: fills the field in the edited locale with a translation of its source
 * text. Only the form state changes; saving the form stores it and marks it `machine`.
 *
 *     RichEditor::make('body')->translateWithAi()
 *     TextInput::make('title')->hintAction(TranslateFieldAction::make())
 */
final class TranslateFieldAction extends Action
{
    /**
     * Adds the action to a form field (used by the `translateWithAi()` macro); anything else is returned unchanged.
     */
    public static function attachTo(mixed $field): mixed
    {
        return $field instanceof Field ? $field->hintAction(self::make()) : $field;
    }

    public static function getDefaultName(): string
    {
        return 'translateField';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(Lang::get('actions.field.label'))
            ->icon(Heroicon::OutlinedLanguage)
            ->hidden(static fn(mixed $livewire, ?Model $record, ActiveLocale $active): bool => ! $record instanceof TracksTranslations
                || $active->isEditingSource($livewire))
            ->action(static function (
                ?Model $record,
                mixed $component,
                mixed $livewire,
                ActiveLocale $active,
                TranslationLocales $locales,
                TextTranslator $texts,
                PendingFieldTranslations $pending,
            ): void {
                $locale = $active->targetOrDefault($livewire);
                if (! $record instanceof TracksTranslations || ! $component instanceof Field || $locale === null) {
                    return;
                }

                $attribute = $component->getName();
                $source = $record->getTranslation($attribute, $locales->source(), false);
                if (! \is_string($source) || trim($source) === '') {
                    return;
                }

                $result = $texts->translate($source, \in_array($attribute, $record->getHtmlTranslatableAttributes(), true), $locale);
                if (! $result->isTranslated() || $result->value === null) {
                    Notification::make()
                        ->title(Lang::get('notifications.field_not_translated'))
                        ->body(implode(' · ', \array_slice($result->issues, 0, 3)))
                        ->warning()
                        ->send();

                    return;
                }

                $component->state($result->value);
                $pending->remember($record, $attribute, $locale, $source);

                Notification::make()
                    ->title(Lang::get('notifications.field_translated'))
                    ->body(Lang::get('notifications.field_translated_body'))
                    ->success()
                    ->send();
            });
    }
}

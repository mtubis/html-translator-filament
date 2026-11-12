<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Actions;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use HtmlTranslator\Filament\Actions\Concerns\HasTranslationForm;
use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Notifications\ReportNotification;
use HtmlTranslator\Filament\Support\ActiveLocale;
use HtmlTranslator\Filament\Support\Lang;
use HtmlTranslator\Filament\Translation\TranslateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Header action for edit pages: translates the record (and the configured relations) from the source locale into the
 * chosen target locale. Hidden while the source locale is being edited.
 *
 *     TranslateRecordAction::make()->relations(['items'])
 */
final class TranslateRecordAction extends Action
{
    use HasTranslationForm;

    /** @var list<string> */
    private array $relations = [];

    public static function getDefaultName(): string
    {
        return 'translateRecord';
    }

    /**
     * @param list<string> $relations relations translated together with the record
     */
    public function relations(array $relations): static
    {
        $this->relations = $relations;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(Lang::get('actions.translate.label'))
            ->icon(Heroicon::OutlinedLanguage)
            ->modalHeading(Lang::get('actions.translate.heading'))
            ->modalDescription(Lang::get('actions.translate.description'))
            ->hidden(static fn(mixed $livewire, ActiveLocale $active): bool => $active->isEditingSource($livewire))
            ->schema(fn(): array => $this->translationFormSchema())
            ->action(function (array $data, ?Model $record, mixed $livewire, ActiveLocale $active, TranslateRecord $translate): void {
                $locale = self::localeFrom($data, $livewire, $active);
                if (! $record instanceof TracksTranslations || $locale === null) {
                    return;
                }

                $report = $translate($record, $locale, self::policyFrom($data), $this->relations);
                ReportNotification::make($report)->send();

                $this->refreshForm($livewire, $record, $locale, $active);
            });
    }

    /**
     * Shows the new values when the page is editing the locale that was translated. EditRecord::refreshFormData()
     * cannot be used: it reads attributesToArray(), which holds every locale of a translatable attribute.
     */
    private function refreshForm(mixed $livewire, Model&TracksTranslations $record, string $locale, ActiveLocale $active): void
    {
        if (! $livewire instanceof EditRecord || ($active->edits($livewire) && $active->target($livewire) !== $locale)) {
            return;
        }

        $attributes = array_values($record->getTranslatableAttributes());
        $values = [];
        foreach ($attributes as $attribute) {
            $values[$attribute] = $record->getTranslation($attribute, $locale, false);
        }

        $livewire->getSchema('form')?->fillPartially($values, $attributes);
    }
}

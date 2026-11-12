<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Actions;

use Filament\Actions\BulkAction;
use Filament\Support\Icons\Heroicon;
use HtmlTranslator\Filament\Actions\Concerns\HasTranslationForm;
use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Notifications\ReportNotification;
use HtmlTranslator\Filament\Support\ActiveLocale;
use HtmlTranslator\Filament\Support\Lang;
use HtmlTranslator\Filament\Translation\TranslateRecord;
use HtmlTranslator\Filament\Translation\TranslationReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Table bulk action: translates every selected record and sends one summary notification. Runs in the request for
 * now; queued in a later release.
 */
final class TranslateBulkAction extends BulkAction
{
    use HasTranslationForm;

    /** @var list<string> */
    private array $relations = [];

    public static function getDefaultName(): string
    {
        return 'translateRecords';
    }

    /**
     * @param list<string> $relations relations translated together with each record
     */
    public function relations(array $relations): static
    {
        $this->relations = $relations;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(Lang::get('actions.bulk.label'))
            ->icon(Heroicon::OutlinedLanguage)
            ->modalHeading(Lang::get('actions.translate.heading'))
            ->modalDescription(Lang::get('actions.translate.description'))
            ->schema(fn(): array => $this->translationFormSchema())
            ->deselectRecordsAfterCompletion()
            ->action(function (array $data, Collection $records, mixed $livewire, ActiveLocale $active, TranslateRecord $translate): void {
                $locale = self::localeFrom($data, $livewire, $active);
                if ($locale === null) {
                    return;
                }

                $reports = [];
                foreach ($records as $record) {
                    if ($record instanceof Model && $record instanceof TracksTranslations) {
                        $reports[] = $translate($record, $locale, self::policyFrom($data), $this->relations);
                    }
                }

                ReportNotification::make(TranslationReport::merge(...$reports))->send();
            });
    }
}

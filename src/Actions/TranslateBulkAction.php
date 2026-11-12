<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Actions;

use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use HtmlTranslator\Filament\Actions\Concerns\HasTranslationForm;
use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Enums\OverwritePolicy;
use HtmlTranslator\Filament\Jobs\TranslateRecordJob;
use HtmlTranslator\Filament\Notifications\ReportNotification;
use HtmlTranslator\Filament\Support\ActiveLocale;
use HtmlTranslator\Filament\Support\Lang;
use HtmlTranslator\Filament\Support\TranslationDefaults;
use HtmlTranslator\Filament\Translation\TranslateRecord;
use HtmlTranslator\Filament\Translation\TranslationReport;
use Illuminate\Auth\AuthManager;
use Illuminate\Bus\Batch;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

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

    /**
     * @param Collection<array-key, mixed> $records
     */
    private function dispatchBatch(Collection $records, string $locale, OverwritePolicy $policy, ?int $userId, TranslationDefaults $defaults): void
    {
        $jobs = [];
        foreach ($records as $record) {
            if ($record instanceof Model && $record instanceof TracksTranslations) {
                $jobs[] = new TranslateRecordJob($record, $locale, $policy, $this->relations, $userId, notify: false);
            }
        }

        $batch = Bus::batch($jobs)
            ->name('html-translator: ' . \count($jobs) . ' records → ' . $locale)
            ->allowFailures()
            ->finally(static function (Batch $batch) use ($userId): void {
                $user = TranslateRecordJob::findUser(Container::getInstance()->make(AuthManager::class), $userId);
                if (!$user instanceof Authenticatable) {
                    return;
                }

                Notification::make()
                    ->title(Lang::get('notifications.batch_done'))
                    ->body(Lang::get('notifications.batch_body', ['done' => $batch->totalJobs - $batch->failedJobs, 'total' => $batch->totalJobs]))
                    ->status($batch->failedJobs === 0 ? 'success' : 'warning')
                    ->sendToDatabase($user);
            })
            ->onQueue($defaults->queueName());

        $connection = $defaults->connection();
        if ($connection !== null) {
            $batch->onConnection($connection);
        }

        $batch->dispatch();

        Notification::make()
            ->title(Lang::get('notifications.started'))
            ->body(Lang::get('notifications.started_body'))
            ->info()
            ->send();
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
            ->action(function (array $data, Collection $records, mixed $livewire, ActiveLocale $active, TranslateRecord $translate, TranslationDefaults $defaults, AuthFactory $auth): void {
                $locale = self::localeFrom($data, $livewire, $active);
                if ($locale === null) {
                    return;
                }

                if ($defaults->queue()) {
                    $this->dispatchBatch($records, $locale, self::policyFrom($data), self::userId($auth), $defaults);

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

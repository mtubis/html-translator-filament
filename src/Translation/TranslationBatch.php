<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Translation;

use HtmlTranslator\Filament\Jobs\TranslateRecordJob;
use HtmlTranslator\Filament\Support\TranslationDefaults;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

/**
 * A batch of translation jobs on the plugin's queue connection and queue. One failed record does not cancel the
 * others. Used by the bulk action and the translate command; callers add their own callbacks before dispatching.
 */
final readonly class TranslationBatch
{
    public function __construct(private TranslationDefaults $defaults) {}

    /**
     * @param list<TranslateRecordJob> $jobs
     */
    public function make(array $jobs, string $name): PendingBatch
    {
        $batch = Bus::batch($jobs)
            ->name('html-translator: ' . $name)
            ->allowFailures()
            ->onQueue($this->defaults->queueName());

        $connection = $this->defaults->connection();
        if ($connection !== null) {
            $batch->onConnection($connection);
        }

        return $batch;
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Posts\Pages;

use Filament\Resources\Pages\EditRecord;
use HtmlTranslator\Filament\Actions\MarkTranslationReviewedAction;
use HtmlTranslator\Filament\Actions\TranslateRecordAction;
use Workbench\App\Filament\Resources\Posts\PostResource;

final class EditPost extends EditRecord
{
    /**
     * The locale being edited, as on lara-zeus/spatie-translatable pages; the plugin's actions read it.
     */
    public ?string $activeLocale = null;

    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TranslateRecordAction::make()->relations(['comments']),
            MarkTranslationReviewedAction::make()->relations(['comments']),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Posts\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use HtmlTranslator\Filament\Actions\MarkTranslationReviewedAction;
use HtmlTranslator\Filament\Concerns\RefreshesAfterTranslation;
use HtmlTranslator\Filament\Tables\Columns\TranslationStatusColumn;

/**
 * Comments are translated together with their post; their badges refresh after the post's translate action.
 */
final class CommentsRelationManager extends RelationManager
{
    use RefreshesAfterTranslation;

    /** The locale being edited, as on lara-zeus/spatie-translatable relation managers. */
    public ?string $activeLocale = null;

    protected static string $relationship = 'comments';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('text'),
                TranslationStatusColumn::make(),
            ])
            ->recordActions([
                MarkTranslationReviewedAction::make(),
            ]);
    }
}

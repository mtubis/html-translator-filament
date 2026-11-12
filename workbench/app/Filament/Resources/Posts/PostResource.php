<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Posts;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use HtmlTranslator\Filament\Actions\MarkTranslationReviewedAction;
use HtmlTranslator\Filament\Actions\TranslateBulkAction;
use HtmlTranslator\Filament\Tables\Columns\TranslationStatusColumn;
use Illuminate\Database\Eloquent\Builder;
use Workbench\App\Filament\Resources\Posts\Pages\EditPost;
use Workbench\App\Filament\Resources\Posts\Pages\ListPosts;
use Workbench\App\Filament\Resources\Posts\RelationManagers\CommentsRelationManager;
use Workbench\App\Models\Post;

/**
 * How an application wires the plugin into a resource: status column, review and bulk actions on the table, the
 * translate action on the edit page.
 */
final class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title'),
            Textarea::make('body'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn(Builder $query): Builder => $query->with('translationStates'))
            ->columns([
                TextColumn::make('title'),
                TranslationStatusColumn::make(),
            ])
            ->recordActions([
                MarkTranslationReviewedAction::make(),
            ])
            ->toolbarActions([
                TranslateBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [CommentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }
}

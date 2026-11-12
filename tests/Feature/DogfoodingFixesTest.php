<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature;

use Filament\Actions\Testing\TestAction;
use HtmlTranslator\Filament\Actions\TranslateRecordAction;
use HtmlTranslator\Filament\Concerns\RefreshesAfterTranslation;
use HtmlTranslator\Filament\Tables\Columns\TranslationStatusColumn;
use HtmlTranslator\Filament\Tests\TestCase;
use HtmlTranslator\Filament\Translation\TranslateRecord;
use HtmlTranslator\Filament\Translation\TranslationReport;
use HtmlTranslator\Laravel\Facades\HtmlTranslation;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Filament\Resources\Posts\Pages\EditPost;
use Workbench\App\Filament\Resources\Posts\RelationManagers\CommentsRelationManager;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

#[CoversClass(TranslateRecordAction::class)]
#[CoversClass(TranslationStatusColumn::class)]
#[CoversClass(TranslationReport::class)]
#[CoversTrait(RefreshesAfterTranslation::class)]
final class DogfoodingFixesTest extends TestCase
{
    #[Test]
    public function a_finished_translation_tells_relation_managers_to_refresh(): void
    {
        $this->actingAs(User::factory()->create());
        HtmlTranslation::fake();

        Livewire::test(EditPost::class, ['record' => Post::factory()->create()->getRouteKey()])
            ->set('activeLocale', 'en')
            ->callAction(TestAction::make('translateRecord'), data: ['policy' => 'only_missing'])
            ->assertDispatched('html-translator-translated');
    }

    #[Test]
    public function relation_managers_with_the_trait_listen_for_finished_translations(): void
    {
        $this->actingAs(User::factory()->create());
        $post = Post::factory()->create();

        $manager = Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $post, 'pageClass' => EditPost::class]);
        $manager->dispatch('html-translator-translated');

        $manager->assertOk();
    }

    #[Test]
    public function the_status_column_is_labelled_as_translation(): void
    {
        app()->setLocale('es');

        self::assertSame('Traducción', TranslationStatusColumn::make()->getLabel());
    }

    #[Test]
    public function empty_sources_are_not_counted_as_skipped(): void
    {
        HtmlTranslation::fake();
        $post = Post::factory()->create(['title' => ['es' => ''], 'body' => ['es' => '<p>Hola</p>']]);

        $report = resolve(TranslateRecord::class)($post, 'en');

        self::assertSame([1, 0, 1], [$report->translated(), $report->skipped(), $report->empty()]);
    }
}

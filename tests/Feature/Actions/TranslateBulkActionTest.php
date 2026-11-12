<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature\Actions;

use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use HtmlTranslator\Filament\Actions\TranslateBulkAction;
use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Tests\TestCase;
use HtmlTranslator\Laravel\Facades\HtmlTranslation;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Filament\Resources\Posts\Pages\ListPosts;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

#[CoversClass(TranslateBulkAction::class)]
final class TranslateBulkActionTest extends TestCase
{
    #[Test]
    public function it_translates_the_selected_records_and_sends_one_notification(): void
    {
        $this->actingAs(User::factory()->create());
        HtmlTranslation::fake(['en' => static fn(string $html): string => 'EN']);
        $first = Post::factory()->create();
        $second = Post::factory()->create();
        $second->setTranslation('title', 'en', 'Reviewed')->save();
        resolve(TranslationStateStore::class)->markReviewed($second, 'title', 'en', null);
        $untouched = Post::factory()->create();

        Livewire::test(ListPosts::class)
            ->selectTableRecords([$first, $second])
            ->callAction(TestAction::make('translateRecords')->table()->bulk(), data: ['policy' => 'only_missing']);

        self::assertSame('EN', $first->refresh()->getTranslation('title', 'en', false));
        self::assertSame('Reviewed', $second->refresh()->getTranslation('title', 'en', false));
        self::assertSame('', $untouched->refresh()->getTranslation('title', 'en', false));
        Notification::assertNotified();
    }
}

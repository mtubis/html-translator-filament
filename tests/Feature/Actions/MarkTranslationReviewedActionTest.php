<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature\Actions;

use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use HtmlTranslator\Filament\Actions\MarkTranslationReviewedAction;
use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Enums\TranslationStatus;
use HtmlTranslator\Filament\Models\TranslationState;
use HtmlTranslator\Filament\Stores\EloquentTranslationStateStore;
use HtmlTranslator\Filament\Tests\TestCase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Filament\Resources\Posts\Pages\EditPost;
use Workbench\App\Filament\Resources\Posts\Pages\ListPosts;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

#[CoversClass(MarkTranslationReviewedAction::class)]
#[CoversClass(EloquentTranslationStateStore::class)]
final class MarkTranslationReviewedActionTest extends TestCase
{
    #[Test]
    public function it_marks_machine_translations_of_the_record_and_its_relations_as_reviewed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $post = Post::factory()->create();
        $comment = Comment::factory()->for($post)->create();
        $store = resolve(TranslationStateStore::class);
        $store->markMachine($post, 'title', 'en', 'Clases de inglés', null, null);
        $store->markMachine($comment, 'text', 'en', '¡Muy buenas clases!', null, null);

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->set('activeLocale', 'en')
            ->callAction(TestAction::make('markTranslationReviewed'));

        $title = $post->translationState('title', 'en');
        self::assertInstanceOf(TranslationState::class, $title);
        self::assertSame(TranslationStatus::Reviewed, $title->status);
        self::assertSame($user->id, $title->reviewed_by);
        self::assertSame(TranslationStatus::Reviewed, $comment->translationState('text', 'en')?->status);
        self::assertSame(TranslationStatus::Missing, $post->translationState('body', 'en')?->status);
        Notification::assertNotified();
    }

    #[Test]
    public function it_speaks_the_application_locale_with_plural_forms(): void
    {
        App::setLocale('pl');
        $this->actingAs(User::factory()->create());
        $post = Post::factory()->create();
        $store = resolve(TranslationStateStore::class);
        $store->markMachine($post, 'title', 'en', 'Clases de inglés', null, null);
        $store->markMachine($post, 'body', 'en', '<p>Con una sonrisa</p>', null, null);

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->set('activeLocale', 'en')
            ->assertActionHasLabel(TestAction::make('markTranslationReviewed'), 'Oznacz jako sprawdzone')
            ->callAction(TestAction::make('markTranslationReviewed'));

        Notification::assertNotified('Oznaczono 2 tłumaczenia jako sprawdzone.');
    }

    #[Test]
    public function it_is_hidden_while_editing_the_source_locale(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(EditPost::class, ['record' => Post::factory()->create()->getRouteKey()])
            ->set('activeLocale', 'es')
            ->assertActionHidden(TestAction::make('markTranslationReviewed'));
    }

    #[Test]
    public function it_works_as_a_table_record_action(): void
    {
        $this->actingAs(User::factory()->create());
        $post = Post::factory()->create();
        resolve(TranslationStateStore::class)->markMachine($post, 'body', 'en', '<p>Aprende</p>', null, null);

        Livewire::test(ListPosts::class)->callAction(TestAction::make('markTranslationReviewed')->table($post));

        self::assertSame(TranslationStatus::Reviewed, $post->translationState('body', 'en')?->status);
    }
}

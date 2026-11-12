<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature\Actions;

use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use HtmlTranslator\Filament\Actions\TranslateRecordAction;
use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Enums\TranslationStatus;
use HtmlTranslator\Filament\Notifications\ReportNotification;
use HtmlTranslator\Filament\Support\ActiveLocale;
use HtmlTranslator\Filament\Tests\Support\FailingTranslation;
use HtmlTranslator\Filament\Tests\TestCase;
use HtmlTranslator\Laravel\Facades\HtmlTranslation;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Filament\Resources\Posts\Pages\EditPost;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

#[CoversClass(TranslateRecordAction::class)]
#[CoversClass(ReportNotification::class)]
#[CoversClass(ActiveLocale::class)]
final class TranslateRecordActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    #[Test]
    public function it_translates_the_record_and_its_relations_and_notifies(): void
    {
        HtmlTranslation::fake(['en' => static fn(string $html): string => 'EN']);
        $post = Post::factory()->create();
        $comment = Comment::factory()->for($post)->create();

        $this->edit($post, 'en')
            ->callAction(TestAction::make('translateRecord'), data: ['policy' => 'only_missing'])
            ->assertHasNoFormErrors();

        self::assertSame('EN', $post->refresh()->getTranslation('title', 'en', false));
        self::assertSame('EN', $comment->refresh()->getTranslation('text', 'en', false));
        Notification::assertNotified();
    }

    #[Test]
    public function it_is_hidden_while_editing_the_source_locale(): void
    {
        $this->edit(Post::factory()->create(), 'es')->assertActionHidden(TestAction::make('translateRecord'));
    }

    #[Test]
    public function the_target_defaults_to_the_locale_being_edited(): void
    {
        $this->edit(Post::factory()->create(), 'en')
            ->mountAction(TestAction::make('translateRecord'))
            ->assertSchemaStateSet(['locale' => 'en', 'policy' => 'only_missing']);
    }

    #[Test]
    public function reviewed_translations_are_kept_unless_forced_with_confirmation(): void
    {
        HtmlTranslation::fake(['en' => static fn(string $html): string => 'NEW']);
        $post = Post::factory()->create();
        $post->setTranslation('title', 'en', 'Reviewed')->save();
        resolve(TranslationStateStore::class)->markReviewed($post, 'title', 'en', null);

        $this->edit($post, 'en')->callAction(TestAction::make('translateRecord'), data: ['policy' => 'overwrite_machine']);
        self::assertSame('Reviewed', $post->refresh()->getTranslation('title', 'en', false));

        $this->edit($post, 'en')
            ->callAction(TestAction::make('translateRecord'), data: ['policy' => 'force'])
            ->assertHasFormErrors(['confirm_force' => 'accepted']);
        self::assertSame('Reviewed', $post->refresh()->getTranslation('title', 'en', false));

        $this->edit($post, 'en')->callAction(
            TestAction::make('translateRecord'),
            data: ['policy' => 'force', 'confirm_force' => true],
        );
        self::assertSame('NEW', $post->refresh()->getTranslation('title', 'en', false));
        self::assertSame(TranslationStatus::Machine, $post->translationState('title', 'en')?->status);
    }

    #[Test]
    public function a_failed_translation_is_reported_with_a_warning(): void
    {
        HtmlTranslation::fake(['en' => static function (string $html): string {
            throw new FailingTranslation('Provider unavailable.');
        }]);

        $this->edit(Post::factory()->create(), 'en')
            ->callAction(TestAction::make('translateRecord'), data: ['policy' => 'only_missing']);

        // The body is built only from the report (ReportNotificationTest checks its content).
        Notification::assertNotified('Draft translation finished with problems');
    }

    #[Test]
    public function the_form_shows_the_new_translation(): void
    {
        HtmlTranslation::fake(['en' => static fn(string $html): string => 'Hello']);
        $post = Post::factory()->create(['body' => null]);

        $this->edit($post, 'en')
            ->callAction(TestAction::make('translateRecord'), data: ['policy' => 'only_missing'])
            ->assertSchemaStateSet(['title' => 'Hello']);
    }

    /**
     * @return Testable<EditPost>
     */
    private function edit(Post $post, string $locale): Testable
    {
        app()->setLocale('en');

        return Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->set('activeLocale', $locale);
    }
}

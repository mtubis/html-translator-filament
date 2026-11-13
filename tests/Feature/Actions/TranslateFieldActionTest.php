<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature\Actions;

use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use HtmlTranslator\Filament\Actions\TranslateFieldAction;
use HtmlTranslator\Filament\Enums\TranslationStatus;
use HtmlTranslator\Filament\Models\TranslationState;
use HtmlTranslator\Filament\Support\PendingFieldTranslations;
use HtmlTranslator\Filament\Support\TranslationDefaults;
use HtmlTranslator\Filament\Tests\Support\FailingTranslation;
use HtmlTranslator\Filament\Tests\TestCase;
use HtmlTranslator\Filament\Translation\TextTranslator;
use HtmlTranslator\Laravel\Facades\HtmlTranslation;
use HtmlTranslator\Laravel\Testing\RecordedTranslation;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Filament\Resources\Posts\Pages\EditPost;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

#[CoversClass(TranslateFieldAction::class)]
#[CoversClass(PendingFieldTranslations::class)]
#[CoversClass(TextTranslator::class)]
final class TranslateFieldActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        app()->setLocale('en');
    }

    #[Test]
    public function it_fills_only_the_field_and_saves_nothing_until_the_form_is_saved(): void
    {
        HtmlTranslation::fake(['en' => static fn(string $html): string => 'English lessons']);
        $post = Post::factory()->create();

        $this->edit($post, 'en')
            ->callAction(TestAction::make('translateField')->schemaComponent('title'))
            ->assertSchemaStateSet(['title' => 'English lessons']);

        self::assertSame('', $post->refresh()->getTranslation('title', 'en', false));
        self::assertSame(TranslationStatus::Missing, $post->translationState('title', 'en')?->status);
        HtmlTranslation::assertTranslatedTimes(1);
    }

    #[Test]
    public function saving_the_form_marks_the_field_as_machine_translated(): void
    {
        HtmlTranslation::fake(['en' => static fn(string $html): string => 'English lessons']);
        $post = Post::factory()->create();

        $this->edit($post, 'en')
            ->callAction(TestAction::make('translateField')->schemaComponent('title'))
            ->call('save')
            ->assertHasNoFormErrors();

        $post->refresh();
        self::assertSame('English lessons', $post->getTranslation('title', 'en', false));
        $state = $post->translationState('title', 'en');
        self::assertInstanceOf(TranslationState::class, $state);
        self::assertSame(TranslationStatus::Machine, $state->status);
        self::assertSame(hash('sha256', 'Clases de inglés'), $state->source_hash);
        self::assertSame(TranslationStatus::Missing, $post->translationState('body', 'en')?->status);
    }

    #[Test]
    public function html_fields_are_sent_as_html_and_text_fields_escaped(): void
    {
        HtmlTranslation::fake();
        $post = Post::factory()->create(['title' => ['es' => 'A & B'], 'body' => ['es' => '<p>Hola</p>']]);

        $this->edit($post, 'en')
            ->callAction(TestAction::make('translateField')->schemaComponent('title'))
            ->callAction(TestAction::make('translateField')->schemaComponent('body'));

        HtmlTranslation::assertTranslated(static fn(RecordedTranslation $t): bool => $t->html === 'A &amp; B');
        HtmlTranslation::assertTranslated(static fn(RecordedTranslation $t): bool => $t->html === '<p>Hola</p>');
    }

    #[Test]
    public function it_is_hidden_while_editing_the_source_locale(): void
    {
        $this->edit(Post::factory()->create(), 'es')
            ->assertActionDoesNotExist(TestAction::make('translateField')->schemaComponent('title'));
    }

    #[Test]
    public function an_incomplete_translation_leaves_the_field_unchanged(): void
    {
        config(['html-translator.providers.broken' => ['driver' => 'pseudo', 'corrupt_every' => 1, 'corrupt_retries' => true]]);
        resolve(TranslationDefaults::class)->configure(null, 'broken');
        $post = Post::factory()->create(['body' => ['es' => '<p>Hola <strong>mundo</strong></p>', 'en' => '<p>old</p>']]);

        $page = $this->edit($post, 'en');
        $before = $page->get('data.body');
        $page->callAction(TestAction::make('translateField')->schemaComponent('body'));

        self::assertSame($before, $page->get('data.body'));

        Notification::assertNotified('The field could not be translated');
    }

    #[Test]
    public function a_failed_translation_leaves_the_field_unchanged(): void
    {
        HtmlTranslation::fake(['en' => static function (string $html): string {
            throw new FailingTranslation('Provider unavailable.');
        }]);
        $post = Post::factory()->create(['title' => ['es' => 'Hola', 'en' => 'Hello']]);

        $page = $this->edit($post, 'en');
        $before = $page->get('data.title');
        $page->callAction(TestAction::make('translateField')->schemaComponent('title'));

        self::assertSame($before, $page->get('data.title'));

        Notification::assertNotified('The field could not be translated');
    }

    #[Test]
    public function saving_without_the_action_marks_nothing(): void
    {
        $post = Post::factory()->create();

        $this->edit($post, 'en')->fillForm(['title' => 'Typed by hand'])->call('save');

        self::assertSame(TranslationStatus::Missing, $post->refresh()->translationState('title', 'en')?->status);
    }

    #[Test]
    public function a_draft_for_a_source_that_changed_meanwhile_is_not_marked(): void
    {
        $post = Post::factory()->create();
        $pending = resolve(PendingFieldTranslations::class);
        $pending->remember($post, 'title', 'en', 'Old source');

        $post->setTranslation('title', 'en', 'Draft')->save();

        self::assertSame(TranslationStatus::Missing, $post->translationState('title', 'en')?->status);
        self::assertSame([], $pending->forRecord($post), 'consumed on save');
    }

    #[Test]
    public function any_field_gets_the_action_through_the_macro(): void
    {
        $field = TextInput::make('title');

        self::assertTrue(Field::hasMacro('translateWithAi'));
        $field->__call('translateWithAi', []);

        self::assertContains('translateField', array_map(static fn(Action $action): ?string => $action->getName(), $field->getHintActions()));
    }

    /**
     * @return Testable<EditPost>
     */
    private function edit(Post $post, string $locale): Testable
    {
        return Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->set('activeLocale', $locale);
    }
}

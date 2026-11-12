<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature;

use Filament\Facades\Filament;
use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Enums\OverwritePolicy;
use HtmlTranslator\Filament\Enums\TranslationStatus;
use HtmlTranslator\Filament\Exceptions\InvalidPluginConfigurationException;
use HtmlTranslator\Filament\HtmlTranslatorPlugin;
use HtmlTranslator\Filament\Models\TranslationState;
use HtmlTranslator\Filament\Support\TranslationDefaults;
use HtmlTranslator\Filament\Tests\Support\FailingTranslation;
use HtmlTranslator\Filament\Tests\TestCase;
use HtmlTranslator\Filament\Translation\AttributeOutcome;
use HtmlTranslator\Filament\Translation\OutcomeStatus;
use HtmlTranslator\Filament\Translation\TranslateRecord;
use HtmlTranslator\Filament\Translation\TranslationReport;
use HtmlTranslator\Laravel\Facades\HtmlTranslation;
use HtmlTranslator\Laravel\Testing\RecordedTranslation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

#[CoversClass(TranslateRecord::class)]
#[CoversClass(TranslationReport::class)]
#[CoversClass(AttributeOutcome::class)]
#[CoversClass(TranslationDefaults::class)]
final class TranslateRecordTest extends TestCase
{
    #[Test]
    public function it_translates_every_source_text_and_marks_the_states_as_machine(): void
    {
        HtmlTranslation::fake(['en' => static fn(string $html): string => '[EN]' . $html]);
        $post = Post::factory()->create(['title' => ['es' => 'Hola'], 'body' => ['es' => '<p>Hola <strong>mundo</strong></p>']]);

        $report = $this->translate($post);

        $post->refresh();
        self::assertSame('[EN]Hola', $post->getTranslation('title', 'en', false));
        self::assertSame('[EN]<p>Hola <strong>mundo</strong></p>', $post->getTranslation('body', 'en', false));
        self::assertSame(2, $report->translated());
        self::assertSame(0, $report->failed() + $report->incomplete() + $report->skipped());

        $state = $post->translationState('body', 'en');
        self::assertInstanceOf(TranslationState::class, $state);
        self::assertSame(TranslationStatus::Machine, $state->status);
        self::assertSame(hash('sha256', '<p>Hola <strong>mundo</strong></p>'), $state->source_hash);
    }

    #[Test]
    public function it_uses_the_plugins_profile_and_the_source_locale(): void
    {
        HtmlTranslation::fake();
        $defaults = resolve(TranslationDefaults::class);
        $defaults->configure('site', null);

        $this->translate(Post::factory()->create());

        HtmlTranslation::assertTranslated(
            static fn(RecordedTranslation $t): bool => $t->profile === 'site' && $t->source === 'es' && $t->target === 'en',
        );
        $post = Post::query()->firstOrFail();
        self::assertInstanceOf(Post::class, $post);
        self::assertSame('site', $post->translationState('title', 'en')?->profile);
    }

    #[Test]
    public function plain_text_is_escaped_for_the_translator_and_stored_as_text(): void
    {
        HtmlTranslation::fake(['en' => static fn(string $html): string => $html]);
        $post = Post::factory()->create(['title' => ['es' => 'Tom & Jerry <3'], 'body' => null]);

        $this->translate($post);

        HtmlTranslation::assertTranslated(static fn(RecordedTranslation $t): bool => $t->html === 'Tom &amp; Jerry &lt;3');
        self::assertSame('Tom & Jerry <3', $post->refresh()->getTranslation('title', 'en', false));
    }

    #[Test]
    public function html_attributes_are_sent_as_html(): void
    {
        HtmlTranslation::fake();

        $this->translate(Post::factory()->create(['title' => null, 'body' => ['es' => '<p>A <em>b</em></p>']]));

        HtmlTranslation::assertTranslated(static fn(RecordedTranslation $t): bool => $t->html === '<p>A <em>b</em></p>');
    }

    #[Test]
    public function only_missing_keeps_machine_and_reviewed_translations(): void
    {
        HtmlTranslation::fake();
        $post = Post::factory()->create();
        $this->store()->markMachine($post, 'title', 'en', 'Clases de inglés', null, null);
        $this->store()->markReviewed($post, 'body', 'en', null);

        $report = $this->translate($post);

        HtmlTranslation::assertNothingTranslated();
        self::assertSame(2, $report->skipped());
    }

    #[Test]
    public function overwrite_machine_retranslates_machine_but_keeps_reviewed(): void
    {
        HtmlTranslation::fake(['en' => static fn(string $html): string => 'new']);
        $post = Post::factory()->create();
        $post->setTranslation('body', 'en', '<p>reviewed</p>')->save();
        $this->store()->markMachine($post, 'title', 'en', 'Clases de inglés', null, null);
        $this->store()->markReviewed($post, 'body', 'en', null);

        $this->translate($post, OverwritePolicy::OverwriteMachine);

        $post->refresh();
        self::assertSame('new', $post->getTranslation('title', 'en', false));
        self::assertSame('<p>reviewed</p>', $post->getTranslation('body', 'en', false));
        self::assertSame(TranslationStatus::Reviewed, $post->translationState('body', 'en')?->status);
    }

    #[Test]
    public function force_overwrites_reviewed_translations_which_become_machine(): void
    {
        HtmlTranslation::fake(['en' => static fn(string $html): string => '<p>new</p>']);
        $post = Post::factory()->create();
        $this->store()->markReviewed($post, 'body', 'en', null);

        $this->translate($post, OverwritePolicy::Force);

        self::assertSame('<p>new</p>', $post->refresh()->getTranslation('body', 'en', false));
        self::assertSame(TranslationStatus::Machine, $post->translationState('body', 'en')?->status);
    }

    #[Test]
    public function empty_sources_are_skipped_without_a_call(): void
    {
        HtmlTranslation::fake();
        $post = Post::factory()->create(['title' => ['es' => ''], 'body' => null]);

        $report = $this->translate($post);

        HtmlTranslation::assertNothingTranslated();
        self::assertSame(0, $report->translated());
    }

    #[Test]
    public function an_incomplete_result_keeps_the_previous_value_and_state(): void
    {
        config(['html-translator.providers.broken' => ['driver' => 'pseudo', 'corrupt_every' => 1, 'corrupt_retries' => true]]);
        resolve(TranslationDefaults::class)->configure(null, 'broken');
        $post = Post::factory()->create(['title' => null, 'body' => ['es' => '<p>Hola <strong>mundo</strong></p>']]);
        $post->setTranslation('body', 'en', '<p>old</p>')->save();

        $report = $this->translate($post);

        self::assertSame(1, $report->incomplete());
        $incomplete = array_values(array_filter($report->outcomes(), static fn(AttributeOutcome $o): bool => $o->status === OutcomeStatus::Incomplete));
        self::assertArrayHasKey(0, $incomplete);
        self::assertNotSame([], $incomplete[0]->issues);
        self::assertSame('<p>old</p>', $post->refresh()->getTranslation('body', 'en', false));
        self::assertSame(TranslationStatus::Missing, $post->translationState('body', 'en')?->status);
    }

    #[Test]
    public function a_failing_attribute_does_not_stop_the_others(): void
    {
        HtmlTranslation::fake(['en' => static function (string $html): string {
            if (str_starts_with($html, '<p>')) {
                throw new FailingTranslation('The provider failed.');
            }

            return 'Hello';
        }]);
        $post = Post::factory()->create(['title' => ['es' => 'Hola'], 'body' => ['es' => '<p>Hola</p>']]);

        $report = $this->translate($post);

        self::assertSame([1, 1], [$report->translated(), $report->failed()]);
        self::assertSame('Hello', $post->refresh()->getTranslation('title', 'en', false));
        $failed = array_values(array_filter($report->outcomes(), static fn(AttributeOutcome $o): bool => $o->status === OutcomeStatus::Failed));
        self::assertArrayHasKey(0, $failed);
        self::assertSame(['The provider failed.'], $failed[0]->issues);
    }

    #[Test]
    public function relations_are_translated_only_when_requested(): void
    {
        HtmlTranslation::fake(['en' => static fn(string $html): string => 'EN']);
        $post = Post::factory()->create();
        $comment = Comment::factory()->for($post)->create();

        $this->translate($post);
        self::assertSame('', $comment->refresh()->getTranslation('text', 'en', false));

        $report = $this->translate($post, OverwritePolicy::OnlyMissing, ['comments']);
        self::assertSame('EN', $comment->refresh()->getTranslation('text', 'en', false));
        self::assertSame(1, $report->translated());
    }

    #[Test]
    public function only_relations_of_tracking_models_can_be_translated(): void
    {
        $post = Post::factory()->create();
        DB::table('users')->insert(['id' => $post->id, 'name' => 'Editor', 'email' => 'e@example.com', 'password' => 'x']);

        $this->expectException(InvalidArgumentException::class);

        $this->translate($post, OverwritePolicy::OnlyMissing, ['untracked']);
    }

    #[Test]
    public function the_target_must_be_one_of_the_plugins_target_locales(): void
    {
        $this->expectException(InvalidPluginConfigurationException::class);

        resolve(TranslateRecord::class)(Post::factory()->create(), 'fr');
    }

    #[Test]
    public function the_report_sums_the_stats_of_every_call(): void
    {
        HtmlTranslation::fake();

        $report = $this->translate(Post::factory()->create());

        self::assertGreaterThanOrEqual(2, $report->stats()['units']);
    }

    #[Test]
    public function the_plugin_registration_publishes_profile_and_provider(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        self::assertSame(HtmlTranslatorPlugin::get()->getProfile(), resolve(TranslationDefaults::class)->profile());
    }

    /**
     * @param list<string> $relations
     */
    private function translate(Post $post, OverwritePolicy $policy = OverwritePolicy::OnlyMissing, array $relations = []): TranslationReport
    {
        return resolve(TranslateRecord::class)($post, 'en', $policy, $relations);
    }

    private function store(): TranslationStateStore
    {
        return resolve(TranslationStateStore::class);
    }
}

<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature\Console;

use HtmlTranslator\Filament\Console\MarkReviewedCommand;
use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Enums\TranslationStatus;
use HtmlTranslator\Filament\Models\TranslationState;
use HtmlTranslator\Filament\Support\RelatedRecords;
use HtmlTranslator\Filament\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

#[CoversClass(MarkReviewedCommand::class)]
#[CoversClass(RelatedRecords::class)]
final class MarkReviewedCommandTest extends TestCase
{
    private const string COMMAND = 'html-translator-filament:mark-reviewed';

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reviewer = User::factory()->create(['email' => 'editor@example.com']);
    }

    #[Test]
    public function it_marks_machine_drafts_and_hand_written_translations_as_reviewed(): void
    {
        $machine = $this->translatedPost('machine');
        $handWritten = Post::factory()->create(['title' => ['es' => 'Hola', 'en' => 'Hello by hand'], 'body' => ['es' => '<p>Hola</p>']]);

        $this->command(['models' => [Post::class], '--reviewer' => 'editor@example.com', '--force' => true])
            ->expectsOutputToContain('Marked as reviewed: 2')
            ->assertSuccessful();

        $state = $this->state($machine, 'title');
        self::assertSame(TranslationStatus::Reviewed, $state->status);
        self::assertSame($this->reviewer->id, $state->reviewed_by);
        self::assertNotNull($state->reviewed_at);
        self::assertSame(TranslationStatus::Reviewed, $this->state($handWritten, 'title')->status);
        self::assertSame(TranslationStatus::Missing, $this->state($handWritten, 'body')->status);
    }

    #[Test]
    public function the_reviewer_can_be_given_by_id(): void
    {
        $post = $this->translatedPost('machine');

        $this->command(['models' => [Post::class], '--reviewer' => (string) $this->reviewer->id, '--force' => true])
            ->assertSuccessful();

        self::assertSame($this->reviewer->id, $this->state($post, 'title')->reviewed_by);
    }

    #[Test]
    public function stale_drafts_are_skipped_and_listed_unless_included(): void
    {
        $post = $this->translatedPost('machine');
        $post->setTranslation('title', 'es', 'Buenas')->save();

        $this->command(['models' => [Post::class], '--reviewer' => 'editor@example.com', '--force' => true])
            ->expectsOutputToContain('Stale, not marked: 1')
            ->expectsOutputToContain($post->getMorphClass() . ' #' . $post->id . ' (en): title')
            ->assertSuccessful();
        self::assertSame(TranslationStatus::Machine, $this->state($post, 'title')->status);

        $this->command(['models' => [Post::class], '--reviewer' => 'editor@example.com', '--include-stale' => true, '--force' => true])
            ->expectsOutputToContain('Of these, stale: 1')
            ->assertSuccessful();
        $state = $this->state($post, 'title');
        self::assertSame(TranslationStatus::Reviewed, $state->status);
        self::assertFalse(resolve(TranslationStateStore::class)->isStale($state));
    }

    #[Test]
    public function a_current_reviewed_translation_is_left_alone(): void
    {
        $other = User::factory()->create();
        $post = $this->translatedPost('reviewed', $other->id);
        $before = DB::table('translation_states')->orderBy('id')->get()->all();

        $this->command(['models' => [Post::class], '--reviewer' => 'editor@example.com', '--force' => true])
            ->expectsOutputToContain('Nothing to mark.')
            ->assertSuccessful();

        self::assertEquals($before, DB::table('translation_states')->orderBy('id')->get()->all());
        self::assertSame($other->id, $this->state($post, 'title')->reviewed_by);
    }

    #[Test]
    public function an_unknown_or_missing_reviewer_changes_nothing(): void
    {
        $post = $this->translatedPost('machine');

        $this->command(['models' => [Post::class], '--reviewer' => 'nobody@example.com', '--force' => true])
            ->expectsOutputToContain('nobody@example.com')
            ->assertFailed();
        $this->command(['models' => [Post::class], '--reviewer' => '999', '--force' => true])->assertFailed();
        $this->command(['models' => [Post::class], '--force' => true])
            ->expectsOutputToContain('--reviewer')
            ->assertFailed();

        self::assertSame(TranslationStatus::Machine, $this->state($post, 'title')->status);
    }

    #[Test]
    public function it_needs_a_confirmation(): void
    {
        $post = $this->translatedPost('machine');

        $this->command(['models' => [Post::class], '--reviewer' => 'editor@example.com', '--no-interaction' => true])
            ->expectsOutputToContain('--force')
            ->assertFailed();
        $this->command(['models' => [Post::class], '--reviewer' => 'editor@example.com'])
            ->expectsConfirmation('Mark 1 translation(s) as reviewed by Editor?', 'no')
            ->assertFailed();

        self::assertSame(TranslationStatus::Machine, $this->state($post, 'title')->status);
    }

    #[Test]
    public function a_dry_run_lists_the_translations_and_changes_nothing(): void
    {
        $post = $this->translatedPost('machine');
        $before = DB::table('translation_states')->orderBy('id')->get()->all();

        $this->command(['models' => [Post::class], '--reviewer' => 'editor@example.com', '--dry-run' => true])
            ->expectsOutputToContain('To mark as reviewed: 1')
            ->expectsOutputToContain($post->getMorphClass() . ' #' . $post->id . ' (en): title')
            ->assertSuccessful();

        self::assertEquals($before, DB::table('translation_states')->orderBy('id')->get()->all());
    }

    #[Test]
    public function relations_and_ids_limit_or_extend_the_work(): void
    {
        $chosen = $this->translatedPost('machine');
        $comment = Comment::factory()->for($chosen)->create(['text' => ['es' => '¡Muy buenas!', 'en' => 'Very good!']]);
        resolve(TranslationStateStore::class)->markMachine($comment, 'text', 'en', '¡Muy buenas!', null, null);
        $other = $this->translatedPost('machine');

        $this->command([
            'models' => [Post::class],
            '--reviewer' => 'editor@example.com',
            '--id' => [(string) $chosen->id],
            '--relations' => ['comments'],
            '--locale' => ['en'],
            '--force' => true,
        ])->expectsOutputToContain('Marked as reviewed: 2')->assertSuccessful();

        self::assertSame(TranslationStatus::Reviewed, $comment->translationState('text', 'en')?->status);
        self::assertSame(TranslationStatus::Machine, $this->state($other, 'title')->status);
    }

    #[Test]
    public function invalid_models_locales_and_relations_fail_before_any_change(): void
    {
        $post = $this->translatedPost('machine');

        $this->command(['models' => ['App\Models\Nope'], '--reviewer' => 'editor@example.com', '--force' => true])->assertFailed();
        $this->command(['models' => [Post::class], '--reviewer' => 'editor@example.com', '--locale' => ['fr'], '--force' => true])->assertFailed();
        $this->command(['models' => [Post::class], '--reviewer' => 'editor@example.com', '--relations' => ['nope'], '--force' => true])
            ->expectsOutputToContain('nope')
            ->assertFailed();

        self::assertSame(TranslationStatus::Machine, $this->state($post, 'title')->status);
    }

    private function translatedPost(string $status, ?int $reviewer = null): Post
    {
        $post = Post::factory()->create(['title' => ['es' => 'Hola', 'en' => 'Hello'], 'body' => null]);
        $store = resolve(TranslationStateStore::class);
        $store->markMachine($post, 'title', 'en', 'Hola', null, null);
        if ($status === 'reviewed') {
            $store->markReviewed($post, 'title', 'en', $reviewer);
        }

        return $post;
    }

    private function state(Post $post, string $attribute): TranslationState
    {
        $state = $post->translationState($attribute, 'en');
        self::assertInstanceOf(TranslationState::class, $state);

        return $state;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function command(array $parameters): PendingCommand
    {
        $command = $this->artisan(self::COMMAND, $parameters);
        self::assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }
}

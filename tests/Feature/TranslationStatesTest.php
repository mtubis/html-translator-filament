<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature;

use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Enums\TranslationStatus;
use HtmlTranslator\Filament\Models\Concerns\HasTranslationStates;
use HtmlTranslator\Filament\Models\TranslationState;
use HtmlTranslator\Filament\Observers\TranslationStateObserver;
use HtmlTranslator\Filament\Stores\EloquentTranslationStateStore;
use HtmlTranslator\Filament\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\Post;

#[CoversClass(TranslationState::class)]
#[CoversClass(TranslationStateObserver::class)]
#[CoversClass(EloquentTranslationStateStore::class)]
#[CoversTrait(HasTranslationStates::class)]
final class TranslationStatesTest extends TestCase
{
    #[Test]
    public function creating_a_record_creates_missing_states_for_its_source_texts_in_every_target_locale(): void
    {
        $post = Post::factory()->create(['title' => ['es' => 'Hola'], 'body' => null]);

        $states = $post->translationStates()->get();

        self::assertCount(1, $states);
        $state = $states->firstOrFail();
        self::assertSame(['title', 'en', TranslationStatus::Missing, null], [
            $state->attribute,
            $state->locale,
            $state->status,
            $state->source_hash,
        ]);
    }

    #[Test]
    public function a_source_text_added_later_gets_a_missing_state(): void
    {
        $post = Post::factory()->create(['body' => null]);
        self::assertNull($post->translationState('body', 'en'));

        $post->setTranslation('body', 'es', '<p>Hola</p>')->save();

        $state = $post->translationStates()->where('attribute', 'body')->where('locale', 'en')->first();
        self::assertSame(TranslationStatus::Missing, $state?->status);
    }

    #[Test]
    public function a_machine_translation_becomes_stale_when_the_source_changes_and_keeps_its_status(): void
    {
        $post = Post::factory()->create(['title' => ['es' => 'Hola']]);
        $this->store()->markMachine($post, 'title', 'en', 'Hola', 'pseudo', 'site');
        self::assertFalse($this->stale($post, 'title'));

        $post->setTranslation('title', 'es', 'Buenas')->save();

        self::assertTrue($this->stale($post, 'title'));
        self::assertSame(TranslationStatus::Machine, $post->translationState('title', 'en')?->status);
    }

    #[Test]
    public function a_reviewed_translation_is_never_downgraded_only_reported_as_stale(): void
    {
        $post = Post::factory()->create(['title' => ['es' => 'Hola']]);
        $this->store()->markMachine($post, 'title', 'en', 'Hola', null, null);
        $this->store()->markReviewed($post, 'title', 'en', null);

        $post->setTranslation('title', 'es', 'Buenas')->save();

        self::assertSame(TranslationStatus::Reviewed, $post->translationState('title', 'en')?->status);
        self::assertTrue($this->stale($post, 'title'));
    }

    #[Test]
    public function a_missing_state_is_never_stale(): void
    {
        $post = Post::factory()->create(['title' => ['es' => 'Hola']]);

        $post->setTranslation('title', 'es', 'Buenas')->save();

        self::assertFalse($this->stale($post, 'title'));
    }

    #[Test]
    public function editing_only_a_target_locale_changes_no_state(): void
    {
        $post = Post::factory()->create();
        $this->store()->markMachine($post, 'title', 'en', 'Clases de inglés', 'pseudo', null);
        $before = DB::table('translation_states')->orderBy('id')->get()->all();

        $this->travel(1)->hours();
        $post->setTranslation('title', 'en', 'English lessons')->save();

        self::assertEquals($before, DB::table('translation_states')->orderBy('id')->get()->all());
    }

    #[Test]
    public function mark_machine_records_the_source_hash_provider_and_profile(): void
    {
        $post = Post::factory()->create();

        $state = $this->store()->markMachine($post, 'body', 'en', '<p>Hola</p>', 'anthropic', 'site');

        self::assertSame(TranslationStatus::Machine, $state->status);
        self::assertSame(hash('sha256', '<p>Hola</p>'), $state->source_hash);
        self::assertSame(['anthropic', 'site'], [$state->provider, $state->profile]);
        self::assertNotNull($state->translated_at);
    }

    #[Test]
    public function mark_reviewed_records_who_and_when(): void
    {
        $userId = DB::table('users')->insertGetId(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'x']);
        $post = Post::factory()->create();
        $this->store()->markMachine($post, 'title', 'en', 'Clases de inglés', null, null);

        $state = $this->store()->markReviewed($post, 'title', 'en', $userId);

        self::assertSame(TranslationStatus::Reviewed, $state->status);
        self::assertSame($userId, $state->reviewed_by);
        self::assertNotNull($state->reviewed_at);
    }

    #[Test]
    public function reviewing_also_marks_hand_written_translations_but_not_empty_ones(): void
    {
        $post = Post::factory()->create();
        $post->setTranslation('title', 'en', 'Typed by an editor')->save();

        $count = $this->store()->markReviewedAll($post, 'en', null);

        self::assertSame(1, $count);
        self::assertSame(TranslationStatus::Reviewed, $post->translationState('title', 'en')?->status);
        self::assertSame(TranslationStatus::Missing, $post->translationState('body', 'en')?->status);
    }

    #[Test]
    public function mark_reviewed_accepts_the_current_source(): void
    {
        $post = Post::factory()->create(['title' => ['es' => 'Hola']]);
        $this->store()->markMachine($post, 'title', 'en', 'Hola', null, null);
        $post->setTranslation('title', 'es', 'Buenas')->save();

        $state = $this->store()->markReviewed($post, 'title', 'en', null);

        self::assertSame(hash('sha256', 'Buenas'), $state->source_hash);
        self::assertFalse($this->stale($post, 'title'));
    }

    #[Test]
    public function reviewing_a_stale_machine_draft_clears_stale(): void
    {
        $post = $this->postWithEnglishTitle('machine');
        $post->setTranslation('title', 'es', 'Buenas')->save();

        self::assertSame(1, $this->store()->markReviewedAll($post, 'en', null));

        self::assertSame(TranslationStatus::Reviewed, $post->translationState('title', 'en')?->status);
        self::assertFalse($this->stale($post, 'title'));
    }

    #[Test]
    public function reviewing_a_stale_reviewed_translation_again_records_the_new_reviewer(): void
    {
        $first = $this->user('first@example.com');
        $second = $this->user('second@example.com');
        $post = $this->postWithEnglishTitle('reviewed', $first);
        $post->setTranslation('title', 'es', 'Buenas')->setTranslation('title', 'en', 'Good afternoon')->save();
        $this->travel(1)->hours();

        self::assertSame(1, $this->store()->markReviewedAll($post, 'en', $second));

        $state = $post->translationState('title', 'en');
        self::assertInstanceOf(TranslationState::class, $state);
        self::assertSame($second, $state->reviewed_by);
        self::assertTrue($state->reviewed_at?->isAfter(now()->subMinute()) ?? false);
        self::assertFalse($this->stale($post, 'title'));
    }

    #[Test]
    public function a_current_reviewed_translation_is_left_alone(): void
    {
        $first = $this->user('first@example.com');
        $post = $this->postWithEnglishTitle('reviewed', $first);
        $before = DB::table('translation_states')->orderBy('id')->get()->all();

        self::assertSame(0, $this->store()->markReviewedAll($post, 'en', $this->user('second@example.com')));

        self::assertEquals($before, DB::table('translation_states')->orderBy('id')->get()->all());
    }

    #[Test]
    public function stale_translations_can_be_excluded(): void
    {
        $machine = $this->postWithEnglishTitle('machine');
        $reviewed = $this->postWithEnglishTitle('reviewed');
        $machine->setTranslation('title', 'es', 'Buenas')->save();
        $reviewed->setTranslation('title', 'es', 'Buenas')->save();
        $before = DB::table('translation_states')->orderBy('id')->get()->all();

        self::assertSame([], $this->store()->reviewable($machine, 'en', includeStale: false));
        self::assertSame(0, $this->store()->markReviewedAll($machine, 'en', null, includeStale: false));
        self::assertSame(0, $this->store()->markReviewedAll($reviewed, 'en', null, includeStale: false));

        self::assertEquals($before, DB::table('translation_states')->orderBy('id')->get()->all());
    }

    #[Test]
    public function reviewable_lists_what_mark_reviewed_all_would_mark(): void
    {
        $post = Post::factory()->create(['title' => ['es' => 'Hola'], 'body' => ['es' => '<p>Hola</p>']]);
        $post->setTranslation('title', 'en', 'Hello')->save();
        $this->store()->markMachine($post, 'title', 'en', 'Hola', null, null);
        $post->setTranslation('title', 'es', 'Buenas')->save();

        $reviewable = $this->store()->reviewable($post, 'en');

        self::assertSame(['title'], array_map(static fn(TranslationState $state): string => $state->attribute, $reviewable));
        self::assertSame(\count($reviewable), $this->store()->markReviewedAll($post, 'en', null));
    }

    #[Test]
    public function there_is_one_state_per_record_attribute_and_locale(): void
    {
        $post = Post::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        $post->translationStates()->create(['attribute' => 'title', 'locale' => 'en']);
    }

    #[Test]
    public function deleting_a_record_deletes_its_states(): void
    {
        $post = Post::factory()->create();

        $post->delete();

        self::assertSame(0, TranslationState::query()->count());
    }

    #[Test]
    public function states_of_records_deleted_without_events_can_be_removed_by_type_and_ids(): void
    {
        $first = Post::factory()->create();
        $second = Post::factory()->create();

        $this->store()->deleteFor($first->getMorphClass(), [$first->id]);

        self::assertFalse($first->translationStates()->exists());
        self::assertTrue($second->translationStates()->exists());
    }

    #[Test]
    public function the_morph_type_follows_the_applications_morph_map(): void
    {
        Relation::enforceMorphMap(['post' => Post::class]);

        try {
            Post::factory()->create();

            self::assertSame(['post'], TranslationState::query()->distinct()->pluck('translatable_type')->all());
        } finally {
            Relation::requireMorphMap(false);
            Relation::morphMap([], false);
        }
    }

    #[Test]
    public function for_record_lists_every_state_of_a_record(): void
    {
        $post = Post::factory()->create();

        self::assertSame(['body', 'title'], $this->store()->forRecord($post)->pluck('attribute')->sort()->values()->all());
    }

    private function store(): TranslationStateStore
    {
        return resolve(TranslationStateStore::class);
    }

    private function postWithEnglishTitle(string $status, ?int $reviewer = null): Post
    {
        $post = Post::factory()->create(['title' => ['es' => 'Hola', 'en' => 'Hello'], 'body' => null]);
        $this->store()->markMachine($post, 'title', 'en', 'Hola', null, null);
        if ($status === 'reviewed') {
            $this->store()->markReviewed($post, 'title', 'en', $reviewer);
        }

        return $post;
    }

    private function user(string $email): int
    {
        return DB::table('users')->insertGetId(['name' => 'Editor', 'email' => $email, 'password' => 'x']);
    }

    private function stale(Post $post, string $attribute): bool
    {
        $state = $post->translationState($attribute, 'en');
        self::assertInstanceOf(TranslationState::class, $state);

        return $this->store()->isStale($state);
    }
}

<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature\Console;

use HtmlTranslator\Filament\Console\TranslateCommand;
use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Enums\TranslationStatus;
use HtmlTranslator\Filament\Jobs\TranslateRecordJob;
use HtmlTranslator\Filament\Support\TranslationDefaults;
use HtmlTranslator\Filament\Support\TranslationLocales;
use HtmlTranslator\Filament\Tests\Support\FailingTranslation;
use HtmlTranslator\Filament\Tests\TestCase;
use HtmlTranslator\Filament\Translation\TranslationBatch;
use HtmlTranslator\Laravel\Facades\HtmlTranslation;
use HtmlTranslator\Laravel\Testing\RecordedTranslation;
use Illuminate\Bus\PendingBatch;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

#[CoversClass(TranslateCommand::class)]
#[CoversClass(TranslationBatch::class)]
final class TranslateCommandTest extends TestCase
{
    private const string COMMAND = 'html-translator-filament:translate';

    #[Test]
    public function it_translates_every_record_into_every_target_locale(): void
    {
        $this->useTargets(['en', 'de']);
        HtmlTranslation::fake([
            'en' => static fn(string $html): string => '[EN]' . $html,
            'de' => static fn(string $html): string => '[DE]' . $html,
        ]);
        $posts = Post::factory()->count(2)->create(['title' => ['es' => 'Hola'], 'body' => ['es' => '<p>Hola</p>']]);

        $this->command(['models' => [Post::class]])
            ->expectsOutputToContain('Translated: 8')
            ->assertSuccessful();

        foreach ($posts as $post) {
            $post->refresh();
            self::assertSame('[EN]Hola', $post->getTranslation('title', 'en', false));
            self::assertSame('[DE]<p>Hola</p>', $post->getTranslation('body', 'de', false));
            self::assertSame(TranslationStatus::Machine, $post->translationState('title', 'de')?->status);
        }
    }

    #[Test]
    public function locale_id_and_relations_options_limit_the_work(): void
    {
        $this->useTargets(['en', 'de']);
        HtmlTranslation::fake(['en' => static fn(string $html): string => 'EN', 'de' => static fn(string $html): string => 'DE']);
        $chosen = Post::factory()->create();
        $comment = Comment::factory()->for($chosen)->create();
        $other = Post::factory()->create();

        $this->command([
            'models' => [Post::class],
            '--locale' => ['en'],
            '--id' => [(string) $chosen->id],
            '--relations' => ['comments'],
        ])->assertSuccessful();

        self::assertSame('EN', $chosen->refresh()->getTranslation('title', 'en', false));
        self::assertSame('', $chosen->getTranslation('title', 'de', false));
        self::assertSame('EN', $comment->refresh()->getTranslation('text', 'en', false));
        self::assertSame('', $other->refresh()->getTranslation('title', 'en', false));
    }

    #[Test]
    public function models_can_be_named_by_their_morph_alias(): void
    {
        Relation::morphMap(['post' => Post::class]);
        HtmlTranslation::fake(['en' => static fn(string $html): string => 'EN']);
        $post = Post::factory()->create();

        try {
            $this->command(['models' => ['post']])->assertSuccessful();
        } finally {
            Relation::morphMap([], false);
        }

        self::assertSame('EN', $post->refresh()->getTranslation('title', 'en', false));
    }

    #[Test]
    public function only_missing_calls_no_provider_for_translated_texts(): void
    {
        HtmlTranslation::fake(['en' => static fn(string $html): string => 'EN']);
        $post = Post::factory()->create(['title' => ['es' => 'Hola'], 'body' => ['es' => '<p>Hola</p>']]);
        resolve(TranslationStateStore::class)->markMachine($post->setTranslation('title', 'en', 'Hi'), 'title', 'en', 'Hola', null, null);
        $post->save();

        $this->command(['models' => [Post::class]])->assertSuccessful();

        HtmlTranslation::assertTranslatedTimes(1);
        self::assertSame('Hi', $post->refresh()->getTranslation('title', 'en', false));
    }

    #[Test]
    public function overwrite_machine_keeps_reviewed_translations(): void
    {
        HtmlTranslation::fake(['en' => static fn(string $html): string => 'NEW']);
        $post = Post::factory()->create(['title' => ['es' => 'Hola'], 'body' => ['es' => '<p>Hola</p>']]);
        $post->setTranslation('title', 'en', 'Machine')->setTranslation('body', 'en', '<p>Reviewed</p>')->save();
        $store = resolve(TranslationStateStore::class);
        $store->markMachine($post, 'title', 'en', 'Hola', null, null);
        $store->markMachine($post, 'body', 'en', '<p>Hola</p>', null, null);
        $store->markReviewed($post, 'body', 'en', null);

        $this->command(['models' => [Post::class], '--policy' => 'overwrite-machine'])->assertSuccessful();

        $post->refresh();
        self::assertSame('NEW', $post->getTranslation('title', 'en', false));
        self::assertSame('<p>Reviewed</p>', $post->getTranslation('body', 'en', false));
    }

    #[Test]
    public function force_needs_the_force_flag_without_interaction(): void
    {
        HtmlTranslation::fake(['en' => static fn(string $html): string => 'NEW']);
        $post = Post::factory()->create(['title' => ['es' => 'Hola'], 'body' => null]);
        $post->setTranslation('title', 'en', 'Reviewed')->save();
        $store = resolve(TranslationStateStore::class);
        $store->markMachine($post, 'title', 'en', 'Hola', null, null);
        $store->markReviewed($post, 'title', 'en', null);

        $this->command(['models' => [Post::class], '--policy' => 'force', '--no-interaction' => true])
            ->expectsOutputToContain('--force')
            ->assertFailed();
        HtmlTranslation::assertNothingTranslated();
        self::assertSame('Reviewed', $post->refresh()->getTranslation('title', 'en', false));

        $this->command(['models' => [Post::class], '--policy' => 'force', '--force' => true, '--no-interaction' => true])
            ->assertSuccessful();
        self::assertSame('NEW', $post->refresh()->getTranslation('title', 'en', false));
    }

    #[Test]
    public function force_asks_for_confirmation_interactively(): void
    {
        HtmlTranslation::fake(['en' => static fn(string $html): string => 'NEW']);
        Post::factory()->create();

        $this->command(['models' => [Post::class], '--policy' => 'force'])
            ->expectsConfirmation('Reviewed translations will be overwritten. Continue?', 'no')
            ->assertFailed();

        HtmlTranslation::assertNothingTranslated();
    }

    #[Test]
    public function a_dry_run_changes_nothing_and_needs_no_provider(): void
    {
        config(['html-translator.default' => 'missing-provider']);
        HtmlTranslation::fake();
        $post = Post::factory()->create(['title' => ['es' => 'Hola'], 'body' => ['es' => '<p>Hola</p>']]);

        $this->command(['models' => [Post::class], '--dry-run' => true])
            ->expectsOutputToContain('Would translate: 2')
            ->assertSuccessful();

        HtmlTranslation::assertNothingTranslated();
        self::assertSame('', $post->refresh()->getTranslation('title', 'en', false));
        self::assertSame(TranslationStatus::Missing, $post->translationState('title', 'en')?->status);
    }

    #[Test]
    public function queue_dispatches_one_batch_on_the_plugins_queue(): void
    {
        Bus::fake();
        resolve(TranslationDefaults::class)->configure(null, null, true, 'database', 'translations');
        Post::factory()->count(3)->create();

        $this->command(['models' => [Post::class], '--queue' => true])
            ->expectsOutputToContain('queue:work database --queue=translations,default')
            ->assertSuccessful();

        Bus::assertBatched(static fn(PendingBatch $batch): bool => $batch->jobs->count() === 3
            && $batch->queue() === 'translations'
            && $batch->connection() === 'database'
            && $batch->jobs->every(static fn(mixed $job): bool => $job instanceof TranslateRecordJob
                && ! $job->notify
                && $job->userId === null
                && $job->locale === 'en'));
    }

    #[Test]
    public function provider_and_profile_overrides_apply_to_a_synchronous_run_only(): void
    {
        HtmlTranslation::fake(['en' => static fn(string $html): string => 'EN']);
        $post = Post::factory()->create();

        $this->command(['models' => [Post::class], '--provider' => 'strong', '--profile' => 'site'])
            ->assertSuccessful();

        HtmlTranslation::assertTranslated(static fn(RecordedTranslation $t): bool => $t->provider === 'strong' && $t->profile === 'site');
        self::assertSame('strong', $post->translationState('title', 'en')?->provider);

        $this->command(['models' => [Post::class], '--queue' => true, '--provider' => 'strong'])
            ->expectsOutputToContain('--provider and --profile')
            ->assertFailed();
    }

    #[Test]
    public function invalid_models_and_locales_fail_before_any_work(): void
    {
        HtmlTranslation::fake();
        Post::factory()->create();

        $this->command(['models' => ['App\Models\Nope']])
            ->expectsOutputToContain('App\Models\Nope')
            ->assertFailed();
        $this->command(['models' => [Post::class, User::class]])
            ->expectsOutputToContain('TracksTranslations')
            ->assertFailed();
        $this->command(['models' => [Post::class], '--locale' => ['fr']])
            ->expectsOutputToContain('fr')
            ->assertFailed();
        $this->command(['models' => [Post::class], '--policy' => 'everything'])
            ->expectsOutputToContain('only-missing')
            ->assertFailed();

        HtmlTranslation::assertNothingTranslated();
    }

    #[Test]
    public function a_failed_attribute_fails_the_command_without_leaking_secrets(): void
    {
        config(['html-translator.providers.openai.api_key' => 'sk-test-SECRET-KEY']);
        HtmlTranslation::fake(['en' => static function (string $html): string {
            throw new FailingTranslation('The provider failed.');
        }]);
        Post::factory()->create();

        $this->command(['models' => [Post::class], '-v' => true])
            ->expectsOutputToContain('The provider failed.')
            ->doesntExpectOutputToContain('sk-test-SECRET-KEY')
            ->assertFailed();
    }

    #[Test]
    public function incomplete_translations_fail_only_on_request(): void
    {
        config(['html-translator.providers.broken' => ['driver' => 'pseudo', 'corrupt_every' => 1, 'corrupt_retries' => true]]);
        Post::factory()->create(['title' => ['es' => 'Hola'], 'body' => ['es' => '<p>Hola <strong>mundo</strong></p>']]);

        $this->command(['models' => [Post::class], '--provider' => 'broken'])
            ->expectsOutputToContain('Incomplete: 2')
            ->assertSuccessful();
        $this->command(['models' => [Post::class], '--provider' => 'broken', '--fail-on-incomplete' => true])
            ->assertFailed();
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

    /**
     * The workbench panel registers es → en; a fresh instance stands in for a panel with more targets.
     *
     * @param list<string> $targets
     */
    private function useTargets(array $targets): void
    {
        $locales = new TranslationLocales();
        $locales->configure('es', $targets);
        app()->instance(TranslationLocales::class, $locales);
        app()->forgetInstance(TranslationStateStore::class);
    }
}

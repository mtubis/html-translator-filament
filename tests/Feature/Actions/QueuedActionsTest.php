<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature\Actions;

use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use HtmlTranslator\Filament\Actions\TranslateBulkAction;
use HtmlTranslator\Filament\Actions\TranslateRecordAction;
use HtmlTranslator\Filament\Enums\OverwritePolicy;
use HtmlTranslator\Filament\Jobs\TranslateRecordJob;
use HtmlTranslator\Filament\Support\TranslationDefaults;
use HtmlTranslator\Filament\Tests\TestCase;
use HtmlTranslator\Laravel\Facades\HtmlTranslation;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Filament\Resources\Posts\Pages\EditPost;
use Workbench\App\Filament\Resources\Posts\Pages\ListPosts;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

#[CoversClass(TranslateRecordAction::class)]
#[CoversClass(TranslateBulkAction::class)]
#[CoversClass(TranslationDefaults::class)]
final class QueuedActionsTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        app()->setLocale('en');
        resolve(TranslationDefaults::class)->configure(null, null, queue: true, connection: 'database', queueName: 'translations');
    }

    #[Test]
    public function the_record_action_queues_a_job_instead_of_translating(): void
    {
        Queue::fake();
        HtmlTranslation::fake();
        $post = Post::factory()->create();

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->set('activeLocale', 'en')
            ->callAction(TestAction::make('translateRecord'), data: ['policy' => 'overwrite_machine']);

        HtmlTranslation::assertNothingTranslated();
        Queue::assertPushedOn('translations', TranslateRecordJob::class, fn(TranslateRecordJob $job): bool => $job->record->is($post)
            && $job->locale === 'en'
            && $job->policy === OverwritePolicy::OverwriteMachine
            && $job->relations === ['comments']
            && $job->userId === $this->user->id
            && $job->notify
            && $job->connection === 'database');
        Notification::assertNotified('Translation started');
    }

    #[Test]
    public function the_bulk_action_dispatches_one_batch(): void
    {
        Bus::fake();
        $posts = Post::factory()->count(3)->create();

        Livewire::test(ListPosts::class)
            ->selectTableRecords($posts->all())
            ->callAction(TestAction::make('translateRecords')->table()->bulk(), data: ['policy' => 'only_missing']);

        Bus::assertBatched(static fn(PendingBatch $batch): bool => $batch->jobs->count() === 3
            && $batch->queue() === 'translations'
            && $batch->jobs->every(static fn(mixed $job): bool => $job instanceof TranslateRecordJob && ! $job->notify));
    }
}

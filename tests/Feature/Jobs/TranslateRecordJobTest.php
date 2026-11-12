<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature\Jobs;

use HtmlTranslator\Filament\Enums\OverwritePolicy;
use HtmlTranslator\Filament\Enums\TranslationStatus;
use HtmlTranslator\Filament\Jobs\TranslateRecordJob;
use HtmlTranslator\Filament\Support\TranslationDefaults;
use HtmlTranslator\Filament\Tests\TestCase;
use HtmlTranslator\Laravel\Facades\HtmlTranslation;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

#[CoversClass(TranslateRecordJob::class)]
final class TranslateRecordJobTest extends TestCase
{
    #[Test]
    public function it_translates_the_record_and_notifies_the_user_in_the_database(): void
    {
        HtmlTranslation::fake(['en' => static fn(string $html): string => 'EN']);
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $comment = Comment::factory()->for($post)->create();

        dispatch_sync(new TranslateRecordJob($post, 'en', OverwritePolicy::OnlyMissing, ['comments'], $user->id));

        self::assertSame('EN', $post->refresh()->getTranslation('title', 'en', false));
        self::assertSame('EN', $comment->refresh()->getTranslation('text', 'en', false));
        self::assertSame(TranslationStatus::Machine, $post->translationState('title', 'en')?->status);
        self::assertSame(1, $user->notifications()->count());
        self::assertStringContainsString('Draft translation ready', $this->notificationData($user));
    }

    #[Test]
    public function jobs_of_a_batch_do_not_notify_individually(): void
    {
        HtmlTranslation::fake();
        $user = User::factory()->create();

        dispatch_sync(new TranslateRecordJob(Post::factory()->create(), 'en', OverwritePolicy::OnlyMissing, [], $user->id, notify: false));

        self::assertSame(0, $user->notifications()->count());
    }

    #[Test]
    public function a_failure_notifies_with_a_generic_message_only(): void
    {
        $user = User::factory()->create();
        $job = new TranslateRecordJob(Post::factory()->create(), 'en', OverwritePolicy::OnlyMissing, [], $user->id);

        $job->failed(new RuntimeException('SQLSTATE: password=sk-SECRET-42 leaked'));

        $data = $this->notificationData($user);
        self::assertStringContainsString('could not be finished', $data);
        self::assertStringNotContainsString('SECRET', $data);
        self::assertStringNotContainsString('SQLSTATE', $data);
    }

    #[Test]
    public function one_record_and_locale_is_never_translated_twice_at_the_same_time(): void
    {
        $post = Post::factory()->create();
        $job = new TranslateRecordJob($post, 'en', OverwritePolicy::OnlyMissing, []);

        $middleware = $job->middleware();

        self::assertArrayHasKey(0, $middleware);
        self::assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
        self::assertSame('html-translator:' . $post->getMorphClass() . ':' . $post->id . ':en', $middleware[0]->key);
        self::assertCount(1, $middleware, 'no rate limiting unless configured');
    }

    #[Test]
    public function a_configured_rate_limit_throttles_the_jobs(): void
    {
        resolve(TranslationDefaults::class)->configure('site', null, rateLimit: 10);

        $middleware = new TranslateRecordJob(Post::factory()->create(), 'en', OverwritePolicy::OnlyMissing, [])->middleware();

        self::assertInstanceOf(RateLimited::class, $middleware[1] ?? null);
    }

    private function notificationData(User $user): string
    {
        $data = DB::table('notifications')->where('notifiable_id', $user->id)->value('data');
        self::assertIsString($data);

        return $data;
    }
}

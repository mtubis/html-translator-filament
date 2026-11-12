<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Jobs;

use Filament\Notifications\Notification;
use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Enums\OverwritePolicy;
use HtmlTranslator\Filament\Notifications\ReportNotification;
use HtmlTranslator\Filament\Support\Lang;
use HtmlTranslator\Filament\Support\TranslationDefaults;
use HtmlTranslator\Filament\Translation\TranslateRecord;
use Illuminate\Auth\AuthManager;
use Illuminate\Bus\Batchable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Translates one record in the background (html-translator-laravel 0.3 will bring a package job; this one is
 * replaced then). One record and locale is never translated twice at the same time, and an optional rate limit
 * keeps bulk runs under the provider's limits.
 */
final class TranslateRecordJob implements ShouldQueue
{
    use Batchable;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /**
     * @param list<string> $relations
     * @param bool $notify false inside a batch: the batch sends one notification
     */
    public function __construct(
        public readonly Model&TracksTranslations $record,
        public readonly string $locale,
        public readonly OverwritePolicy $policy,
        public readonly array $relations = [],
        public readonly ?int $userId = null,
        public readonly bool $notify = true,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        $key = $this->record->getKey();
        $middleware = [
            new WithoutOverlapping(\sprintf(
                'html-translator:%s:%s:%s',
                $this->record->getMorphClass(),
                \is_scalar($key) ? (string) $key : '',
                $this->locale,
            ))->releaseAfter(30)->expireAfter(900),
        ];

        if ($this->defaults()->rateLimit() !== null) {
            $middleware[] = new RateLimited(TranslationDefaults::RATE_LIMITER);
        }

        return $middleware;
    }

    public function handle(TranslateRecord $translate, AuthManager $auth): void
    {
        if ($this->batch()?->cancelled() === true) {
            return;
        }

        $report = $translate($this->record, $this->locale, $this->policy, $this->relations);

        $user = $this->user($auth);
        if ($this->notify && $user instanceof Authenticatable) {
            ReportNotification::make($report)->sendToDatabase($user);
        }
    }

    /**
     * The exception is not shown: unlike the translator's messages, it may come from anywhere (database, network)
     * and contain anything.
     */
    public function failed(?Throwable $exception): void
    {
        $user = $this->user(Container::getInstance()->make(AuthManager::class));
        if (!$user instanceof Authenticatable) {
            return;
        }

        Notification::make()
            ->title(Lang::get('notifications.failed'))
            ->body(Lang::get('notifications.failed_body'))
            ->danger()
            ->sendToDatabase($user);
    }

    private function user(AuthManager $auth): ?Authenticatable
    {
        return self::findUser($auth, $this->userId);
    }

    /**
     * The user provider of the default guard: no user model has to be configured for the plugin.
     */
    public static function findUser(AuthManager $auth, ?int $userId): ?Authenticatable
    {
        $guard = $auth->guard();
        if ($userId === null || ! method_exists($guard, 'getProvider')) {
            return null;
        }

        $provider = $guard->getProvider();

        return $provider instanceof UserProvider ? $provider->retrieveById($userId) : null;
    }

    private function defaults(): TranslationDefaults
    {
        return Container::getInstance()->make(TranslationDefaults::class);
    }
}

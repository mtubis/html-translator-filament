<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature;

use HtmlTranslator\Filament\Notifications\ReportNotification;
use HtmlTranslator\Filament\Tests\TestCase;
use HtmlTranslator\Filament\Translation\AttributeOutcome;
use HtmlTranslator\Filament\Translation\OutcomeStatus;
use HtmlTranslator\Filament\Translation\TranslationReport;
use HtmlTranslator\TranslationStats;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ReportNotification::class)]
final class ReportNotificationTest extends TestCase
{
    #[Test]
    public function a_complete_run_is_a_success_with_counts_and_usage(): void
    {
        app()->setLocale('en');
        $report = new TranslationReport(
            [$this->outcome('title', OutcomeStatus::Translated), $this->outcome('body', OutcomeStatus::Skipped)],
            [new TranslationStats(units: 3, batches: 1, retries: 0, durationSeconds: 0.5, inputTokens: 120, outputTokens: 80, cacheHits: 1)],
        );

        $notification = ReportNotification::make($report);

        self::assertSame('success', $notification->getStatus());
        $body = (string) $notification->getBody();
        self::assertStringContainsString('Translated: 1', $body);
        self::assertStringContainsString('Skipped: 1', $body);
        self::assertStringContainsString('200 tokens', $body);
        self::assertStringContainsString('1 from cache', $body);
    }

    #[Test]
    public function problems_make_it_a_warning_and_issues_are_listed_shortened(): void
    {
        app()->setLocale('en');
        $long = str_repeat('x', 300);
        $report = new TranslationReport([
            $this->outcome('title', OutcomeStatus::Failed, ['Provider unavailable.']),
            $this->outcome('body', OutcomeStatus::Incomplete, [$long, 'second', 'third', 'fourth']),
        ]);

        $notification = ReportNotification::make($report);

        self::assertSame('warning', $notification->getStatus());
        $body = (string) $notification->getBody();
        self::assertStringContainsString('Provider unavailable.', $body);
        self::assertStringNotContainsString($long, $body);
        self::assertStringNotContainsString('fourth', $body, 'at most three issues are listed');
    }

    /**
     * @param list<string> $issues
     */
    private function outcome(string $attribute, OutcomeStatus $status, array $issues = []): AttributeOutcome
    {
        return new AttributeOutcome('post', 1, $attribute, $status, null, $issues);
    }
}

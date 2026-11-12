<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Notifications;

use Filament\Notifications\Notification;
use HtmlTranslator\Filament\Support\Lang;
use HtmlTranslator\Filament\Translation\AttributeOutcome;
use HtmlTranslator\Filament\Translation\TranslationReport;

/**
 * Turns a TranslationReport into a Filament notification. Built only from counts, usage and the translator's issue
 * messages: never prompt text, translations or keys.
 */
final class ReportNotification
{
    private const int MAX_ISSUES = 3;

    private const int MAX_ISSUE_LENGTH = 140;

    public static function make(TranslationReport $report): Notification
    {
        $problems = $report->failed() + $report->incomplete();

        return Notification::make()
            ->title(Lang::get($problems === 0 ? 'notifications.translated' : 'notifications.translated_with_problems'))
            ->body(self::body($report))
            ->status($problems === 0 ? 'success' : 'warning');
    }

    private static function body(TranslationReport $report): string
    {
        $lines = [Lang::get('notifications.counts', [
            'translated' => $report->translated(),
            'skipped' => $report->skipped(),
            'incomplete' => $report->incomplete(),
            'failed' => $report->failed(),
        ])];

        $stats = $report->stats();
        if ($stats['input_tokens'] !== null || $stats['output_tokens'] !== null) {
            $lines[] = Lang::get('notifications.usage', [
                'tokens' => ($stats['input_tokens'] ?? 0) + ($stats['output_tokens'] ?? 0),
                'cache_hits' => $stats['cache_hits'],
            ]);
        }

        $issues = [];
        foreach ($report->outcomes() as $outcome) {
            foreach ($outcome->issues as $issue) {
                $issues[] = self::issue($outcome, $issue);
            }
        }

        if ($issues !== []) {
            $lines[] = Lang::get('notifications.issues') . ' ' . implode(' · ', \array_slice($issues, 0, self::MAX_ISSUES));
        }

        return implode("\n", $lines);
    }

    private static function issue(AttributeOutcome $outcome, string $message): string
    {
        $text = $outcome->attribute . ': ' . $message;

        return mb_strlen($text) > self::MAX_ISSUE_LENGTH ? mb_substr($text, 0, self::MAX_ISSUE_LENGTH - 1) . '…' : $text;
    }
}

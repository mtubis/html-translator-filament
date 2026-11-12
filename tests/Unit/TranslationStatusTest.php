<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Unit;

use HtmlTranslator\Filament\Enums\TranslationStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslationStatus::class)]
final class TranslationStatusTest extends TestCase
{
    #[Test]
    public function it_has_the_documented_values(): void
    {
        self::assertSame(
            ['missing', 'machine', 'reviewed'],
            array_map(static fn(TranslationStatus $status): string => $status->value, TranslationStatus::cases()),
        );
    }

    #[Test]
    public function every_status_has_a_badge_colour(): void
    {
        self::assertSame(
            ['gray', 'warning', 'success'],
            array_map(static fn(TranslationStatus $status): string => $status->getColor(), TranslationStatus::cases()),
        );
    }
}

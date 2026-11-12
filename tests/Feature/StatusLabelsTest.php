<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature;

use HtmlTranslator\Filament\Enums\TranslationStatus;
use HtmlTranslator\Filament\Tests\TestCase;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(TranslationStatus::class)]
final class StatusLabelsTest extends TestCase
{
    #[Test]
    public function labels_are_translated(): void
    {
        App::setLocale('es');
        self::assertSame(['Sin traducir', 'Automática', 'Revisada'], $this->labels());

        App::setLocale('en');
        self::assertSame(['Not translated', 'Machine', 'Reviewed'], $this->labels());
    }

    /**
     * @return list<string>
     */
    private function labels(): array
    {
        return array_map(static fn(TranslationStatus $status): string => $status->getLabel(), TranslationStatus::cases());
    }
}

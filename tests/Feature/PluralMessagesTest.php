<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature;

use HtmlTranslator\Filament\Support\Lang;
use HtmlTranslator\Filament\Tests\TestCase;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Lang::class)]
final class PluralMessagesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function reviewedCounts(): iterable
    {
        yield 'en 1' => ['en', 1, '1 translation marked as reviewed.'];
        yield 'en 2' => ['en', 2, '2 translations marked as reviewed.'];
        yield 'es 1' => ['es', 1, '1 traducción marcada como revisada.'];
        yield 'es 3' => ['es', 3, '3 traducciones marcadas como revisadas.'];
        yield 'de 1' => ['de', 1, '1 Übersetzung als geprüft markiert.'];
        yield 'de 2' => ['de', 2, '2 Übersetzungen als geprüft markiert.'];
        // Polish: one, few (2–4, 22–24, … but not 12–14), many.
        yield 'pl 1' => ['pl', 1, 'Oznaczono 1 tłumaczenie jako sprawdzone.'];
        yield 'pl 3' => ['pl', 3, 'Oznaczono 3 tłumaczenia jako sprawdzone.'];
        yield 'pl 5' => ['pl', 5, 'Oznaczono 5 tłumaczeń jako sprawdzone.'];
        yield 'pl 12' => ['pl', 12, 'Oznaczono 12 tłumaczeń jako sprawdzone.'];
        yield 'pl 22' => ['pl', 22, 'Oznaczono 22 tłumaczenia jako sprawdzone.'];
    }

    #[Test]
    #[DataProvider('reviewedCounts')]
    public function the_reviewed_count_uses_the_plural_form_of_the_locale(string $locale, int $count, string $expected): void
    {
        App::setLocale($locale);

        self::assertSame($expected, Lang::choice('actions.review.done', $count));
    }
}

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
     * Polish has three forms; 1, 2, 5, 12, 22 and 25 cover each of them and the 12–14 exception.
     *
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function counts(): iterable
    {
        yield 'en 1' => ['en', 'actions.review.done', 1, '1 translation marked as reviewed.'];
        yield 'en 2' => ['en', 'actions.review.done', 2, '2 translations marked as reviewed.'];
        yield 'es 1' => ['es', 'actions.review.done', 1, '1 traducción marcada como revisada.'];
        yield 'es 3' => ['es', 'actions.review.done', 3, '3 traducciones marcadas como revisadas.'];
        yield 'de 1' => ['de', 'actions.review.done', 1, '1 Übersetzung als geprüft markiert.'];
        yield 'de 2' => ['de', 'actions.review.done', 2, '2 Übersetzungen als geprüft markiert.'];
        yield 'de stale 1' => ['de', 'actions.review.stale_warning', 1, '1 Übersetzung ist nicht aktuell, weil sich der Ausgangstext geändert hat. Markieren Sie sie nur dann als geprüft, wenn Sie sie mit dem aktuellen Ausgangstext verglichen haben.'];
        yield 'de stale 2' => ['de', 'actions.review.stale_warning', 2, '2 Übersetzungen sind nicht aktuell, weil sich der Ausgangstext geändert hat. Markieren Sie sie nur dann als geprüft, wenn Sie sie mit dem aktuellen Ausgangstext verglichen haben.'];

        foreach ([1 => 'tłumaczenie', 2 => 'tłumaczenia', 5 => 'tłumaczeń', 12 => 'tłumaczeń', 22 => 'tłumaczenia', 25 => 'tłumaczeń'] as $n => $noun) {
            yield "pl {$n}" => ['pl', 'actions.review.done', $n, "Oznaczono {$n} {$noun} jako sprawdzone."];
        }

        foreach ([1 => 'tłumaczenie, które było', 2 => 'tłumaczenia, które były', 5 => 'tłumaczeń, które były', 12 => 'tłumaczeń, które były', 22 => 'tłumaczenia, które były', 25 => 'tłumaczeń, które były'] as $n => $words) {
            yield "pl done stale {$n}" => ['pl', 'actions.review.done_stale', $n, "W tym {$n} {$words} nieaktualne."];
        }

        // French: 0 and 1 are singular.
        yield 'fr 0' => ['fr', 'actions.review.done', 0, '0 traduction marquée comme vérifiée.'];
        yield 'fr 1' => ['fr', 'actions.review.done', 1, '1 traduction marquée comme vérifiée.'];
        yield 'fr 2' => ['fr', 'actions.review.done', 2, '2 traductions marquées comme vérifiées.'];

        // Ukrainian: one (1, 21, not 11) | few (2–4, 22–24, not 12–14) | many.
        foreach ([1 => 'переклад як перевірений', 2 => 'переклади як перевірені', 5 => 'перекладів як перевірені', 11 => 'перекладів як перевірені', 21 => 'переклад як перевірений', 22 => 'переклади як перевірені', 25 => 'перекладів як перевірені'] as $n => $words) {
            yield "uk {$n}" => ['uk', 'actions.review.done', $n, "Позначено {$n} {$words}."];
        }

        // Chinese: one form.
        yield 'zh_CN 1' => ['zh_CN', 'actions.review.done', 1, '已将 1 条翻译标记为已审核。'];
        yield 'zh_CN 5' => ['zh_CN', 'actions.review.done', 5, '已将 5 条翻译标记为已审核。'];

        foreach ([1, 2, 5, 12] as $n) {
            yield "pl stale {$n}" => ['pl', 'actions.review.stale_warning', $n, "Nieaktualne tłumaczenia: {$n}. Tekst źródłowy się zmienił. Przed oznaczeniem tłumaczeń jako sprawdzonych porównaj je z aktualnym tekstem źródłowym."];
        }
    }

    #[Test]
    #[DataProvider('counts')]
    public function counts_use_the_plural_forms_of_the_locale(string $locale, string $key, int $count, string $expected): void
    {
        App::setLocale($locale);

        self::assertSame($expected, Lang::choice($key, $count));
    }
}

<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Unit;

use HtmlTranslator\Filament\Exceptions\InvalidPluginConfigurationException;
use HtmlTranslator\Filament\Support\TranslationLocales;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslationLocales::class)]
final class TranslationLocalesTest extends TestCase
{
    #[Test]
    public function it_is_not_configured_until_a_panel_registers_the_plugin(): void
    {
        $locales = new TranslationLocales();

        self::assertFalse($locales->isConfigured());
        self::assertSame([], $locales->targets());
    }

    #[Test]
    public function it_keeps_the_source_and_target_locales(): void
    {
        $locales = new TranslationLocales();
        $locales->configure('es', ['en', 'pt-BR']);

        self::assertTrue($locales->isConfigured());
        self::assertSame('es', $locales->source());
        self::assertSame(['en', 'pt-BR'], $locales->targets());
    }

    #[Test]
    public function the_same_configuration_may_be_registered_by_several_panels(): void
    {
        $locales = new TranslationLocales();
        $locales->configure('es', ['en']);
        $locales->configure('es', ['en']);

        self::assertSame(['en'], $locales->targets());
    }

    #[Test]
    public function panels_must_not_disagree_on_the_locales(): void
    {
        $locales = new TranslationLocales();
        $locales->configure('es', ['en']);

        $this->expectException(InvalidPluginConfigurationException::class);
        $this->expectExceptionMessage('html-translator: every panel must use the same locales (es → en, got es → en, fr).');

        $locales->configure('es', ['en', 'fr']);
    }

    #[Test]
    public function the_source_is_required_once_configured(): void
    {
        $this->expectException(InvalidPluginConfigurationException::class);

        new TranslationLocales()->source();
    }
}

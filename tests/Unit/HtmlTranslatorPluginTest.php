<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Unit;

use HtmlTranslator\Exception\TranslatorException;
use HtmlTranslator\Filament\Exceptions\InvalidPluginConfigurationException;
use HtmlTranslator\Filament\HtmlTranslatorPlugin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HtmlTranslatorPlugin::class)]
#[CoversClass(InvalidPluginConfigurationException::class)]
final class HtmlTranslatorPluginTest extends TestCase
{
    #[Test]
    public function it_has_a_stable_id(): void
    {
        self::assertSame('html-translator', HtmlTranslatorPlugin::make()->getId());
    }

    #[Test]
    public function it_defaults_to_the_package_profile_and_provider_and_translates_in_the_request(): void
    {
        $plugin = HtmlTranslatorPlugin::make();

        self::assertNull($plugin->getProfile());
        self::assertNull($plugin->getProvider());
        self::assertFalse($plugin->shouldQueue());
    }

    #[Test]
    public function it_keeps_the_fluent_configuration(): void
    {
        $plugin = HtmlTranslatorPlugin::make()
            ->sourceLocale('es')
            ->locales(['es', 'en', 'pt-BR'])
            ->profile('site')
            ->provider('anthropic')
            ->queue();

        self::assertSame('es', $plugin->getSourceLocale());
        self::assertSame(['es', 'en', 'pt-BR'], $plugin->getLocales());
        self::assertSame(['en', 'pt-BR'], $plugin->getTargetLocales());
        self::assertSame('site', $plugin->getProfile());
        self::assertSame('anthropic', $plugin->getProvider());
        self::assertTrue($plugin->shouldQueue());
    }

    #[Test]
    public function the_source_locale_must_be_one_of_the_locales(): void
    {
        $this->expectException(InvalidPluginConfigurationException::class);
        $this->expectExceptionMessage('html-translator: the source locale "fr" is not one of the locales (es, en).');

        HtmlTranslatorPlugin::make()->locales(['es', 'en'])->sourceLocale('fr')->getTargetLocales();
    }

    #[Test]
    public function locales_must_be_unique_non_empty_strings(): void
    {
        $this->expectException(InvalidPluginConfigurationException::class);

        HtmlTranslatorPlugin::make()->locales(['es', 'es']);
    }

    #[Test]
    public function an_empty_locale_is_rejected(): void
    {
        $this->expectException(InvalidPluginConfigurationException::class);

        HtmlTranslatorPlugin::make()->locales(['es', ' ']);
    }

    #[Test]
    public function locales_are_required_before_use(): void
    {
        $this->expectException(InvalidPluginConfigurationException::class);
        $this->expectExceptionMessage('html-translator: call locales() with the source and target locales.');

        HtmlTranslatorPlugin::make()->sourceLocale('es')->getTargetLocales();
    }

    #[Test]
    public function configuration_errors_can_be_caught_as_translator_exceptions(): void
    {
        $this->expectException(TranslatorException::class);

        HtmlTranslatorPlugin::make()->getSourceLocale();
    }
}

<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature;

use Filament\Facades\Filament;
use HtmlTranslator\Filament\HtmlTranslatorFilamentServiceProvider;
use HtmlTranslator\Filament\HtmlTranslatorPlugin;
use HtmlTranslator\Filament\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\Post;

#[CoversClass(HtmlTranslatorPlugin::class)]
#[CoversClass(HtmlTranslatorFilamentServiceProvider::class)]
final class PanelRegistrationTest extends TestCase
{
    #[Test]
    public function the_workbench_panel_has_the_configured_plugin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $plugin = HtmlTranslatorPlugin::get();

        self::assertSame('es', $plugin->getSourceLocale());
        self::assertSame(['en'], $plugin->getTargetLocales());
    }

    #[Test]
    public function the_fixture_model_stores_translations(): void
    {
        $post = Post::factory()->create(['title' => ['es' => 'Hola'], 'body' => ['es' => '<p>Hola <strong>mundo</strong></p>']]);

        $post->setTranslation('title', 'en', 'Hello')->save();

        self::assertSame('Hello', $post->refresh()->getTranslation('title', 'en'));
        self::assertSame('<p>Hola <strong>mundo</strong></p>', $post->getTranslation('body', 'es'));
    }

    #[Test]
    public function the_package_translations_are_loaded(): void
    {
        self::assertSame('HTML translator', trans('html-translator-filament::messages.plugin_name', [], 'en'));
        self::assertSame('Traductor HTML', trans('html-translator-filament::messages.plugin_name', [], 'es'));
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Filament\Panel;
use Filament\PanelProvider;
use HtmlTranslator\Filament\HtmlTranslatorPlugin;
use Workbench\App\Filament\Resources\Posts\PostResource;

/**
 * The panel the plugin is developed and tested against: Spanish source, English target.
 */
final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->resources([PostResource::class])
            ->databaseNotifications()
            ->plugin(
                HtmlTranslatorPlugin::make()
                    ->sourceLocale('es')
                    ->locales(['es', 'en']),
            );
    }
}

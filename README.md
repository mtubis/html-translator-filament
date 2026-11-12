# html-translator for Filament

A Filament v5 plugin for markup-safe AI translation of `spatie/laravel-translatable` models. It is built on
[`mtubis/html-translator-laravel`](https://github.com/mtubis/html-translator-laravel) and adds record and field
actions, a review workflow (`machine` → `reviewed`) and stale-translation detection.

> **Status:** early development, not released. Today the package registers a configurable panel plugin; the actions
> arrive with 0.1 (see [docs/issues/0.1](docs/issues/0.1) and [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)).

## Requirements
PHP 8.4+, Laravel 13, Filament 5, `mtubis/html-translator-laravel` ^0.2 (configured with at least one provider).

## Installation
```bash
composer require mtubis/html-translator-filament
```

Register the plugin on a panel:

```php
use HtmlTranslator\Filament\HtmlTranslatorPlugin;

$panel->plugin(
    HtmlTranslatorPlugin::make()
        ->sourceLocale('es')          // defaults to the first locale
        ->locales(['es', 'en'])       // source + targets
        ->profile('site')             // an entry of config/html-translator.php; null = the package default
        ->provider(null)              // likewise
        ->queue(),                    // translate in queued jobs (0.1 #5)
);
```

## Development
See [CONTRIBUTING.md](CONTRIBUTING.md). `composer check` runs Pint, Rector, PHPStan and PHPUnit. `composer serve`
starts the workbench panel.

## License
MIT, see [LICENSE](LICENSE).

# Repository bootstrap: package skeleton, workbench, tooling, CI

## Context
`docs/ARCHITECTURE.md`. Same conventions
as `mtubis/html-translator-laravel`: PHP ^8.4, `declare(strict_types=1)`, `final`, PSR-12, English everywhere.

## Scope
- `composer.json`:
  - name `mtubis/html-translator-filament`, namespace `HtmlTranslator\Filament\`;
  - requires `php ^8.4`, `filament/filament ^5`, `mtubis/html-translator-laravel ^0.2`;
  - suggests `spatie/laravel-translatable ^6`, the first supported storage target;
  - the Laravel package auto-discovers the service provider.
- `HtmlTranslatorPlugin` (Filament `Plugin`):
  - `make()`, `sourceLocale()`, `locales()`, `profile()`, `provider()`, `queue()` (see the PLAN API draft);
  - it does nothing visible yet, apart from being registered.
- Tests:
  - Orchestra Testbench + a **workbench** app with a Filament panel and a translatable `Post` model with an HTML
    `body` and a plain `title`. The model is the fixture for every later issue.
  - PHPUnit with attributes and snake_case names; `HtmlTranslation::fake()` / the `pseudo` driver, so there is no
    network.
- Tooling: Pint, PHPStan (Larastan, level max), Rector, `composer check`; GitHub Actions CI on PHP 8.4.
- Docs: README (installation, path-repository development, status 0.x), CHANGELOG, LICENSE (MIT), ADR-0001, and
  `docs/ARCHITECTURE.md` from `docs/ARCHITECTURE.md`.

## Acceptance criteria
- [ ] `composer check` green locally and in CI; the workbench panel boots in a test with the plugin registered.
- [ ] The dogfooding app can `composer require mtubis/html-translator-filament:@dev` through a path repository.

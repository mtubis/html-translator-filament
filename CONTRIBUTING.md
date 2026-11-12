# Contributing

## Setup
```bash
composer install
composer check          # Pint (test mode), Rector dry-run, PHPStan (max), PHPUnit
```

- `composer test`, `composer test:unit`, `composer test:feature`
- `composer lint` (Pint fix), `composer rector:fix`, `composer stan`
- `composer serve`: the Testbench workbench (a Filament panel at `/admin`)

Tests never use the network: they run with the `pseudo` provider of html-translator-laravel or
`HtmlTranslation::fake()`.

## Developing against an application
Clone this repository next to the application and add a path repository there:

```json
"repositories": [{ "type": "path", "url": "../html-translator-filament", "options": { "symlink": true } }]
```

```bash
composer require mtubis/html-translator-filament:@dev
```

Make changes in this repository (branch, tests, PR), not in the application's `vendor/`.

## Commits
Conventional Commits in English (`feat:`, `fix:`, `docs:`, `chore:`, `ci:`), with `Refs #<issue>` when there is an
issue.

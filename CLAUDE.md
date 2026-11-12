# html-translator-filament

Filament v5 plugin for markup-safe AI translation of translatable models, built on `mtubis/html-translator-laravel`
(which wraps the core `mtubis/html-translator`). It adds record and field actions, a review workflow
(`missing` → `machine` → `reviewed`), stale-translation detection and queued translation. All translation logic lives
in the core and the Laravel package; this package is the Filament UI and workflow on top.

## Read before planning
- `docs/ARCHITECTURE.md`: public API draft, behaviour, milestones.
- `docs/adr/`: accepted decisions. Do not contradict an accepted ADR; propose a new one.
- `docs/issues/<milestone>/`: issue drafts; their acceptance criteria are the definition of done.
- The Laravel package's README and `src/Contracts`, `src/Facades`, `src/Testing` in
  `vendor/mtubis/html-translator-laravel/`. Read them, never edit them.

## Commands
PHP needs `intl` (Filament). If the host lacks it, run everything in a PHP 8.4 container with `intl`.
- `composer check`: Pint (test mode), Rector dry-run, PHPStan (Larastan, max, strict rules), PHPUnit. Must be green
  before you say you are done.
- `composer test` · `composer stan` · `composer lint` · `composer rector:fix` · `composer serve` (workbench)

## Rules
- PHP ^8.4, Laravel ^13, Filament ^5. `declare(strict_types=1);`, `final` classes, `readonly` where possible,
  constructor injection.
- **Laravel package public API only**: `HtmlTranslator\Laravel\Contracts\TranslatesHtml` (inject it, so
  `HtmlTranslation::fake()` works), the facade in tests, the core result's public methods and value objects. Never
  use the package's internals or the core's pipeline classes. If something is missing, draft an issue for
  `mtubis/html-translator-laravel` and tell me.
- No facades or `config()`/`app()` helpers inside `src/`, except service providers and Filament conventions that
  require them (`HtmlTranslatorPlugin::get()` uses `filament()`).
- Every exception implements `HtmlTranslator\Exception\TranslatorException`.
- Secrets (API keys, header values) never appear in exception messages, notifications, logs or test snapshots. Test
  this explicitly in code that touches providers or notifications.
- Never overwrite a `reviewed` translation without an explicit `Force` policy.
- User-facing strings come from `lang/` (`en`, `es`), under the `html-translator-filament` namespace.
- English for code, comments, docs and commits. Ask before adding a Composer dependency.
- Public repository: don't mention private client projects or their people by name in code, docs, commits or
  issues. Say "the dogfooding app".

## Testing conventions
- PHPUnit attributes, `#[CoversClass]` on every test (strict coverage metadata), snake_case behaviour names,
  `self::assert*`.
- `tests/Unit`: no container. `tests/Feature`: extend `Tests\TestCase` (Testbench + Filament + the workbench panel and
  `Post` fixture).
- No network: the `pseudo` provider or `HtmlTranslation::fake()`.

## Don't
- Don't edit `vendor/`, `.github/` or `composer.json` dependencies unless the issue asks for it. Don't push or open
  PRs unless asked.

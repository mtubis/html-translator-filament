# Architecture

The initial plan. Turn sections into ADRs as decisions are made.

## Positioning
Several Filament plugins already "translate fields with AI" (e.g. `backtik-ch/filament-translatable` over
`laravel/ai`, `martin6363/filament-ai-translator`). They send a field's text to a model and paste the answer back.
This plugin's reason to exist is what the core does and they don't:

- **Markup-safe rich text**: inline tags stay on the right words, structure validated, corrective retries, fallbacks.
- **Profiles and glossaries** (`do_not_translate`, term policy, formality) configured once per panel.
- **Unit cache**: re-translating an edited page pays only for changed paragraphs.
- **Review workflow**: machine drafts are tracked (`machine` → `reviewed`), stale translations are detected via a hash
  of the source.
- **Any provider** the Laravel package supports, including local models (Ollama, vLLM) and the `pseudo` driver.

## Requirements
PHP ^8.4, Laravel ^13, Filament ^5, `mtubis/html-translator-laravel` ^0.2 (own queued job until the package's 0.3
ships one, then switch), `spatie/laravel-translatable` ^6
(suggested, first supported target; the target is behind a contract so other storage can follow).

## Public API (draft)
```php
// Panel
$panel->plugin(HtmlTranslatorPlugin::make()
    ->sourceLocale('es')
    ->locales(['es', 'en'])
    ->profile('site')                 // entry of config/html-translator.php
    ->provider(null)                  // null = package default
    ->queue(true));                   // dispatch jobs instead of translating in the request

// Record / table actions
TranslateRecordAction::make()         // header action on Edit pages: all translatable attributes (+ relations opt-in)
TranslateBulkAction::make()           // table bulk action
MarkTranslationReviewedAction::make()

// Field-level, works on the form state (nothing saved until the editor saves)
RichEditor::make('body')->translateWithAi();
TextInput::make('title')->translateWithAi();

// Table / infolist
TranslationStatusColumn::make()       // badge per locale: missing | machine | reviewed | stale
```

## Behaviour
- Source values are read from the source locale; HTML attributes are sent as HTML, plain-text attributes as escaped
  text. Empty source → skipped.
- Overwrite policy: `onlyMissing` (default) | `overwriteMachine` | `force` (also overwrites `reviewed`, needs
  confirmation).
- Result notification: units, batches, tokens, cache hits; issues listed per attribute; attributes with issues are
  marked and keep their previous value according to the fallback strategy.
- States stored in the plugin's `translation_states` table (publishable migration), written through an interface so an
  app can store them elsewhere.
- Events from the Laravel package (0.3, when released) are re-used for logging/cost tracking; the plugin adds none it
  doesn't need.

## Milestones
- **0.1**: issues in `docs/issues/0.1/`, in this order:
  1. bootstrap;
  2. translation states;
  3. `TranslateRecord` (with opt-in relations);
  4. actions + status column;
  5. queue + notifications;
  6. field action.
- **0.2**: "Translations" page (issues, token usage, stale), an action form with provider / profile / target locales,
  side-by-side diff; switch to `html-translator-laravel` 0.3 jobs/events.
- **0.3**: repeaters, Builder blocks, other storage targets.

## Development setup
In the site repository:
```json
"repositories": [{ "type": "path", "url": "../html-translator-filament", "options": { "symlink": true } }]
```
`composer require mtubis/html-translator-filament:@dev` — remove the path repository before releasing the site.

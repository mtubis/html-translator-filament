# Artisan command: translate records from the command line

GitHub: https://github.com/mtubis/html-translator-filament/issues/2

## Context
In 0.1 records are translated only from the panel: a field, a record, or the selected rows of a table. Translating a
whole site for the first time, catching up after a batch of source edits, a scheduled run or a CI step all need a
command. `mtubis/html-translator-laravel` has `html-translator:translate`, but it translates an HTML file and knows
nothing about models or translation states.

The command is a thin layer over the public API that already exists: `TranslateRecord`, `TranslateRecordJob`,
`TranslationStateStore`, and the plugin's locales and defaults, which `HtmlTranslatorPlugin::register()` puts in the
container, so they are available in the console too.

## Scope
```bash
php artisan html-translator-filament:translate "App\Models\Post" --locale=en --policy=only-missing --relations=comments
```

- `--locale=` (repeatable): default every target locale of the plugin.
- `--policy=`: `only-missing` (default), `overwrite-machine` or `force`.
- `--relations=` (repeatable): like `->relations()` on the actions.
- `--id=` (repeatable): only these records.
- `--queue`: dispatch jobs (a batch) instead of translating in the process.
- `--dry-run`: say what would be translated, call no provider.

- **Models:** one or more arguments, a class name or a morph-map alias. Each must implement `TracksTranslations`;
  anything else fails before any work with a clear message.
- **Selection:** records are read in chunks (`lazyById`), so a large table does not load into memory. With
  `only-missing`, records with nothing missing in a locale are skipped without a provider call, using the states.
- **Policies** as in the actions. `force` overwrites reviewed translations: it asks for confirmation, and in
  non-interactive mode (`--no-interaction`, cron, CI) it needs `--force` as well, or it stops.
- **Synchronous mode (default):** a progress bar per locale, then a table per record (translated, skipped,
  incomplete, failed) and totals: units, tokens, cache hits. The translator's issue messages with `-v`.
- **`--queue`:** dispatches `TranslateRecordJob`s as one batch on the plugin's connection and queue, prints the
  batch id and the worker command to run (`queue:work --queue=…`). No user to notify: database notifications are not
  sent. `--queue` without `->queue()` on the plugin uses the default connection and queue.
- **`--dry-run`:** per record and locale, which attributes would be translated under the policy, and the totals. No
  provider is resolved, so it works without a key.
- **Provider and profile:** the plugin's defaults; `--provider=` and `--profile=` override them for one run.
- **Exit codes:** `0` when nothing failed; `1` when an attribute failed or a job could not be dispatched;
  incomplete translations are warnings, unless `--fail-on-incomplete`.
- **Secrets:** no prompt text, translations or keys in the output, at any verbosity.
- **Several panels:** the settings of the last registered panel apply (as for jobs today); documented.
- README: a "Command line" section with the first full translation, a scheduled run
  (`Schedule::command(...)->daily()`), and the `--dry-run` / `--queue` examples.

## Out of scope
- Translating without states (models that do not implement `TracksTranslations`).
- Translating reviewed-but-stale translations as a separate policy (0.4 "Translations" page).
- Cost estimates in currency.

## Acceptance criteria
- [ ] Feature tests with the workbench models and the `pseudo` driver or `HtmlTranslation::fake()`:
  - translates every record into every target locale and marks the states `machine`;
  - `--locale`, `--id`, `--relations` limit the work;
  - `only-missing` skips translated attributes without a provider call; `overwrite-machine` replaces machine
    translations and keeps reviewed ones;
  - `force` without `--force` in non-interactive mode exits with an error and changes nothing;
  - `--dry-run` changes nothing and calls no provider;
  - `--queue` dispatches one batch of `TranslateRecordJob` on the configured connection and queue (`Bus::fake()`);
  - a model that does not implement `TracksTranslations`, or an unknown class or alias, fails with a message;
  - exit code `1` on a failed attribute; `--fail-on-incomplete` turns incomplete into `1`.
- [ ] The output never contains an API key (test with a fake key in config).
- [ ] README "Command line" section; CHANGELOG entry under `[Unreleased]`.
- [ ] `composer check` green.

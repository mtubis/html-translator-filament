# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/).
While the version is 0.x, a minor version may change the public API; this file says how.

## [Unreleased]

## [0.2.0] - 2026-11-13

Translate and review whole sites from the command line, see at a glance which reviewed translations are out of date,
and use the panel in seven languages.

### Upgrading
- **Custom `TranslationStateStore` implementations** must add the `bool $includeStale = true` parameter to
  `markReviewedAll()` and implement the new `reviewable()` method. Applications using the shipped Eloquent store need
  no change.
- The status column now shows a separate *Stale* badge next to the state (`EN · Reviewed` `EN · Stale`). Tests that
  assert the column state get one more item for stale records.

### Added
- `php artisan html-translator-filament:translate`: translate the records of one or more models (class names or morph
  aliases) into the target locales, with `--policy`, `--locale`, `--relations`, `--id`, `--dry-run`, `--queue`,
  `--provider` / `--profile` and `--fail-on-incomplete` (#2).
- `php artisan html-translator-filament:mark-reviewed`: mark the translations of many records as reviewed on behalf of
  a reviewer (`--reviewer`, required), with a confirmation, `--dry-run`, and stale translations left out unless
  `--include-stale` (#2).
- Interface languages: German and Polish (#4), French, Ukrainian and Chinese (Mandarin, Simplified) (#5), next to
  English and Spanish. The README lists who reviewed each language; native input is welcome.
- Plural forms for texts with a count, by each language's rules (Polish and Ukrainian have three).
- A tooltip on the *Stale* badge explaining why the translation is out of date.
- `TranslateRecord::preview()`: what a translation would do, without calling the translator.
- README: **Translating in the panel (for editors)**, a step-by-step guide for the people who translate and review
  content; **Command line**; **Interface languages**, with how to change a text or add a language.

### Fixed
- **Mark as reviewed** clears *Stale*: reviewing accepts a translation for the current source text, also when it was
  corrected by hand after the source changed. Stale reviewed translations can be reviewed again (#3).

### Changed
- The status column keeps the state visible when a translation is stale (see *Upgrading*).
- The **Mark as reviewed** confirmation says how many of the translations are stale; the success notification
  counts them.
- `TranslationStateStore::markReviewed()` and `markReviewedAll()` store the current source hash (see *Upgrading* for
  the new parameter and method).
- Spanish texts revised: "Borrador" for machine drafts, "texto de origen" throughout.
- Internal: the bulk action and the translate command share `TranslationBatch`; translating and reviewing share
  `Support\RelatedRecords`. No behaviour change.

## [0.1.2] - 2026-11-13

Documentation only: how to set up a provider and run the queue worker, without reading the code.

### Added
- README: **Choosing a provider and a model**: publishing `config/html-translator.php`, the `.env` keys and model
  variables per provider, `html-translator:check --ping`, the `pseudo` provider for work without an API key, and
  that the default provider is `openai` when `HTML_TRANSLATOR_PROVIDER` is not set.
- README: **Queue** explains which queues the worker must listen on (the plugin's queue and `default`, where
  Filament's database notifications go), development and production workers, the required tables, what the editor
  sees after a queued translation, and a troubleshooting table.

## [0.1.1] - 2026-11-13

### Fixed
- The `translation_states` migration is published with the current timestamp (`YYYY_MM_DD_HHMMSS_…`) like other
  package migrations; 0.1.0 published it without a date prefix, so it sorted after all dated migrations.

### Changed
- Package description and keywords (`i18n`, `localization`) in `composer.json`.

## [0.1.0] - 2026-11-13

First release: translate spatie-translatable records from a Filament 5 panel with
[html-translator-laravel](https://github.com/mtubis/html-translator-laravel), review the drafts and see which
translations are missing, unreviewed or stale.

### Added
- **Panel plugin**: `HtmlTranslatorPlugin` with source and target locales, profile, provider, queue (connection and
  queue name) and an optional rate limit.
- **Translation states**: publishable `translation_states` migration (a no-op when the table exists), `missing` →
  `machine` → `reviewed`, stale detection from a hash of the source text. `TracksTranslations` contract,
  `HasTranslationStates` trait, an observer that creates `missing` states, and the `TranslationStateStore` contract
  with an Eloquent implementation.
- **`TranslateRecord`**: translates a record and opt-in relations into one locale. HTML attributes are declared on
  the model (`$htmlTranslatable`); plain text is escaped. Overwrite policies `OnlyMissing`, `OverwriteMachine` and
  `Force` (the only one that touches reviewed translations). Only complete results are written, in one transaction
  after the model calls, and a `TranslationReport` gives per-attribute outcomes and summed usage.
- **Filament**: `TranslateRecordAction`, `MarkTranslationReviewedAction` (machine drafts and hand-written
  translations), `TranslateBulkAction`, `TranslationStatusColumn`, the field action `TranslateFieldAction` /
  `->translateWithAi()`, and `RefreshesAfterTranslation` for relation managers. They read the edited locale from a
  public `activeLocale` (as on lara-zeus/spatie-translatable pages).
- **Queue**: `TranslateRecordJob` with overlap protection per record and locale, optional rate limiting, database
  notifications with the report, and one batch with one notification for bulk runs.
- **Safety**: notifications contain counts, usage and the translator's issue messages only: never prompt text,
  translations, keys or exception details from outside the translator.
- English and Spanish UI strings.

[Unreleased]: https://github.com/mtubis/html-translator-filament/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/mtubis/html-translator-filament/compare/v0.1.2...v0.2.0
[0.1.2]: https://github.com/mtubis/html-translator-filament/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/mtubis/html-translator-filament/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/mtubis/html-translator-filament/releases/tag/v0.1.0

# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/).
While the version is 0.x, a minor version may change the public API; this file says how.

## [Unreleased]

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

[Unreleased]: https://github.com/mtubis/html-translator-filament/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/mtubis/html-translator-filament/releases/tag/v0.1.0

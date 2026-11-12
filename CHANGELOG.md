# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Package skeleton: `HtmlTranslatorPlugin` (panel configuration: source and target locales, profile, provider,
  queue), service provider with `en`/`es` translations, `InvalidPluginConfigurationException`.
- Testbench workbench with a Filament panel and a translatable `Post` fixture; Pint, Rector, PHPStan (max) and
  PHPUnit via `composer check`; GitHub Actions CI.
- Translation states: publishable `translation_states` migration (a no-op when the table exists),
  `TranslationStatus` enum with Filament labels/colours, `TranslationState` model with stale detection,
  `TracksTranslations` contract and `HasTranslationStates` trait, an observer creating `missing` states for new source
  texts, and the `TranslationStateStore` contract with an Eloquent implementation.
- `HtmlTranslatorPlugin::register()` publishes the locales (`TranslationLocales`) for models, observers and jobs;
  panels must agree on them.
- `TranslateRecord`: translates a record (and opt-in relations) into a target locale. HTML vs plain-text attributes
  (`$htmlTranslatable`), overwrite policies (`OnlyMissing`, `OverwriteMachine`, `Force`), complete results only, one
  transaction after the model calls, and a `TranslationReport` with outcomes and summed stats.
- `TranslationDefaults` (profile/provider published by the plugin); `TracksTranslations` gains
  `getHtmlTranslatableAttributes()` and `setTranslation()`; `TranslationStateStore::status()`.
- Filament layer: `TranslateRecordAction` (locale + overwrite policy, confirmation for `Force`, report
  notification, form refresh), `MarkTranslationReviewedAction` (page and table), `TranslateBulkAction` (synchronous
  for now), `TranslationStatusColumn` (missing / machine / stale / reviewed per target locale), `ReportNotification`,
  and English/Spanish UI strings.
- `TranslationStateStore::markReviewedAll()`, `TranslationReport::merge()`, `Support\ActiveLocale` (reads a public
  `activeLocale`, as on lara-zeus/spatie-translatable pages).
- Mutation testing (Infection, as in html-translator-laravel) is planned once there is logic worth mutating.

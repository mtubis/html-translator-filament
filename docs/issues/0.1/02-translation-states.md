# Translation states: migration, model, staleness, observer — behind a contract

## Context
`docs/ARCHITECTURE.md` "Behaviour". The schema matches the table the dogfooding app already has, so it can switch to the plugin
without a data migration.

## Scope
- Publishable migration `create_translation_states_table` (dated file name, so `vendor:publish` stamps it):
  - morphs `translatable`, `attribute`, `locale`, `status` (`missing|machine|reviewed`), `source_hash` (sha256);
  - `translated_at`, `reviewed_at`, `reviewed_by` (nullable FK to `users`; a configurable user table and the
    reviewer relation are deferred to #4, where the review action needs them);
  - `provider`, `profile`, timestamps;
  - unique (`translatable_type`, `translatable_id`, `attribute`, `locale`).
  - The migration skips creation if the table already exists, as it does in the dogfooding app.
- `TranslationStatus` enum with Filament `HasLabel`/`HasColor`. The `TranslationState` model has an
  `isStale(string $sourceLocale)` method rather than an accessor, because the model must not read global state for
  the source locale. `TranslationStateStore::isStale()` supplies it. A state is stale when the hash of the current
  source differs from `source_hash`; `missing` is never stale.
- Contract `TranslationStateStore` with an Eloquent implementation (`forRecord`, `markMachine`, `markReviewed`,
  `ensureMissing`). Apps can bind their own.
- Models implement `Contracts\TracksTranslations` (spatie's `HasTranslations` plus this package's
  `HasTranslationStates`). The locales come from `Support\TranslationLocales`, which `HtmlTranslatorPlugin::register()`
  fills, so console, seeders and jobs know them too.
- `HasTranslationStates` trait (`translationStates()` morphMany) and an observer that creates `missing` rows for the
  plugin's target locales when a source value appears. It never changes existing rows; deletes cascade.
- Short morph types are the app's choice (the docs recommend `enforceMorphMap`); the plugin never assumes class names.

## Acceptance criteria
- [ ] Tests on the workbench `Post`:
  - missing rows on create;
  - stale after a source edit, with the status kept (`reviewed` is never downgraded);
  - editing only a target changes nothing;
  - unique constraint;
  - cascade on delete;
  - a migration run against an existing table is a no-op.
- [ ] `composer check` green.

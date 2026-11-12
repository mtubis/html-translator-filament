# `TranslateRecord`: translate a record's attributes (and opt-in relations) into a locale

## Context
`docs/ARCHITECTURE.md` "Behaviour". Uses `mtubis/html-translator-laravel` only through `Contracts\TranslatesHtml` and the result's
public API. The states come from #2. Filament code (#4) calls this service and contains no translation logic.

## Scope
- `TranslateRecord::__invoke(Model $record, string $locale, OverwritePolicy $policy): TranslationReport`.
  - Records are spatie-translatable models in 0.1. Relations to translate together are configured per
    resource/action (`->withRelations(['items'])`), never guessed.
- Attribute kinds:
  - HTML attributes are declared by the app (e.g. `->html(['body'])`) and sent as HTML;
  - all other translatable attributes are escaped (`e()`) before sending and decoded after, so `<`/`&` in a title can
    never become markup;
  - empty sources are skipped.
- `->from(source)`, plus the plugin's profile and provider from `HtmlTranslatorPlugin`.
- `OverwritePolicy`:
  - `OnlyMissing` (default);
  - `OverwriteMachine`;
  - `Force`, the only policy that overwrites `reviewed`; the caller must choose it explicitly.
- Writing:
  - `setTranslation()`;
  - the state becomes `machine` with `source_hash`, `translated_at` and provider/profile names (never keys);
  - incomplete results keep the previous target value and are reported;
  - writes happen in one transaction per record, after all model calls, so no transaction stays open during
    network calls.
- `TranslationReport` (readonly): translated/skipped/failed attributes, merged stats (units, tokens, cache hits),
  issues per attribute.
- A `TranslatorException` for one attribute is caught and reported, and the others continue. Messages come from the
  library, which never includes keys.

## Acceptance criteria
- [ ] Tests on the workbench `Post` (+ a relation fixture) with `HtmlTranslation::fake()`:
  - all policies, including that `reviewed` is untouched without `Force`;
  - plain-text escaping;
  - an incomplete result keeps the old value;
  - one failing attribute does not stop the others;
  - relations only when configured;
  - `assertTranslated` sees the plugin's `profile` and `source`.
- [ ] Friction with the library goes to `docs/notes/laravel-package.md` (input for html-translator-laravel 0.3) and
      becomes upstream issue drafts. No reaching into the library's internals.
- [ ] `composer check` green.

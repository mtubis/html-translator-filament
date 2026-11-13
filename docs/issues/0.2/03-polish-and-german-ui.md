# Polish and German interface translations

GitHub: https://github.com/mtubis/html-translator-filament/issues/4

## Context
The plugin's interface texts (action labels, modal forms, states, notifications) live in `lang/{locale}/messages.php`
and follow the application locale, like Filament's own texts. 0.1 ships English and Spanish. Filament itself is
translated into Polish (`pl`) and German (`de`), so a Polish or German panel currently shows the plugin's buttons in
English next to Filament's translated ones.

## Scope
- `lang/pl/messages.php` and `lang/de/messages.php` with every key of `lang/en/messages.php`.
- Terminology consistent with Filament's own `pl` / `de` files (e.g. how they say *record*, *bulk action*, *save*),
  and with the states as editors meet them: *Not translated*, *Machine*, *Stale*, *Reviewed*.
- Placeholders (`:count`, `:done`, `:total`, `:tokens`, …) kept exactly; plural forms where the text has a count
  (Polish has three: 1 / 2–4 / 5+, via Laravel's `{1} … |[2,4] … |[5,*] …` syntax or a neutral wording).
- Reviewed by a native speaker before release (the PR names who reviewed each language).
- A test that every `lang/*/messages.php` has exactly the keys of `lang/en/messages.php` and keeps its placeholders,
  so a new key cannot be forgotten in one language.
- README: the list of shipped interface languages, and how to override or add one
  (`lang/vendor/html-translator-filament/{locale}/messages.php`); contributions of new languages welcome.

## Out of scope
- French, Ukrainian, Chinese (Mandarin, `zh_CN`): later releases, see `docs/ARCHITECTURE.md`.
- Translating the README.

## Acceptance criteria
- [ ] `pl` and `de` files complete; the key/placeholder parity test passes for `en`, `es`, `pl`, `de`.
- [ ] Livewire test: with the application locale set to `pl` (and `de`), the record action label and the status
      badge text are the translated ones.
- [ ] Native-speaker review noted in the PR.
- [ ] README and CHANGELOG (`[Unreleased]`, *Added*) updated.
- [ ] `composer check` green.

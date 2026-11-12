# Filament: TranslateRecordAction, MarkTranslationReviewedAction, TranslationStatusColumn, TranslateBulkAction

## Context
`docs/ARCHITECTURE.md` public API. Actions call `TranslateRecord` (#3) and the state store (#2). UI strings are translatable
(`lang/` in the package, `en` and `es` shipped).

## Scope
- `TranslateRecordAction` (header action on Edit pages that use lara-zeus/spatie-translatable):
  - visible when the active locale is not the source;
  - a modal with the policy choice, and an extra confirmation for `Force`;
  - it refreshes the form in the active locale afterwards.
- `MarkTranslationReviewedAction`:
  - turns `machine` states of the record (and configured relations) in the active locale into `reviewed`, with
    `reviewed_at`/`reviewed_by`;
  - there is also a record action for relation managers.
  - Editing a target by hand does not change its state, because review is explicit.
- `TranslationStatusColumn`: one badge per target locale (`missing` / `machine` / `reviewed` / `stale`), with
  colours meeting AA contrast.
- `TranslateBulkAction` (synchronous in this issue; #5 queues it).
- Notifications show counts, tokens, cache hits and issues (truncated). They **never** contain prompt text, model
  responses or keys.

## Acceptance criteria
- [ ] Livewire tests on the workbench panel with `HtmlTranslation::fake()`:
  - each action;
  - the policy choice;
  - review sets the user;
  - the badges for all four states;
  - the action is hidden in the source locale;
  - no key in notifications (fake key in config).
- [ ] `composer check` green; screenshots in the PR.

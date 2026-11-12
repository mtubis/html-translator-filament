# Field action `->translateWithAi()` on RichEditor / TextInput

## Context
`docs/ARCHITECTURE.md` public API. It works on the form state only: nothing is saved until the editor saves, and the state becomes
`machine` on save.

## Scope
- A macro or extension on `RichEditor` and `TextInput`: a suffix action, visible when the active locale is not the
  source.
- It reads the source value from the record and translates it with the plugin's profile/provider: HTML for
  `RichEditor`, escaped text for `TextInput`.
- It fills the field in the active locale and remembers that the field was machine-filled, so the save marks it
  `machine`.
- A notification with issues if the result is incomplete. The field keeps its previous value.

## Acceptance criteria
- [ ] Livewire tests on the workbench:
  - the action fills only that field and saves nothing until save;
  - after save the state is `machine`;
  - the action is hidden in the source locale;
  - an incomplete result leaves the field unchanged.
- [ ] `composer check` green.

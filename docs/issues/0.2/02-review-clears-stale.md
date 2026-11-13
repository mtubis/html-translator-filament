# Marking a stale translation as reviewed should clear "Stale"

GitHub: https://github.com/mtubis/html-translator-filament/issues/3

## Context
A translation is *stale* when the source text changed after it was made: `translation_states.source_hash` no longer
matches the current source. Only a new machine translation refreshes the hash today (`markMachine`).

The natural editor flow does not:

1. The source text of a translated (machine or reviewed) attribute changes, the badge shows **Stale**.
2. The editor corrects the translation by hand in the target locale and saves.
3. The editor clicks **Mark as reviewed**.

Result: the badge still shows **Stale**. `markReviewedAll()` skips states that are already `reviewed`, and for
`machine` states it changes the status but keeps the old `source_hash`. The only workaround is to generate a new AI
draft first (field **Translate** action), which throws away the editor's own correction. The README documents this
as a known limitation of 0.1.

## Scope
- **Reviewing means "this translation matches the current source".** When a translation is marked reviewed, its
  `source_hash` is set to the hash of the current source text, so it is no longer stale.
- `markReviewedAll()` also includes **stale reviewed** translations (today they are skipped), and refreshes the
  hash of stale machine drafts. Reviewed translations that are not stale stay untouched (no new reviewer or date).
- `markReviewed()` (one attribute) refreshes the hash the same way.
- **Panel:** the confirmation of **Mark as reviewed** says how many of the translations are stale ("2 of them are
  marked stale: their source text changed. Confirm only if you have checked them against the current source.").
  The success notification counts them separately.
- **Command line (#2):** `mark-reviewed` keeps skipping stale translations by default; with `--include-stale` it
  marks them and refreshes their hash through the same store method.
- **API:** `TranslationStateStore::markReviewedAll()` gets a parameter that says whether stale translations are
  included (the panel passes `true`, the command passes `--include-stale`). The interface is public: a custom store
  must add it, so the CHANGELOG lists this under *Changed* for 0.2.
- README: remove the *Known limitation (0.1)* note from the editor guide; "When the source text changes" describes
  both ways: a new AI draft, or a correction by hand, followed by **Mark as reviewed**.

## Acceptance criteria
- [ ] Store tests:
  - a stale machine draft marked reviewed is reviewed and no longer stale;
  - a stale reviewed translation marked reviewed again gets the new reviewer, date and hash, and is no longer stale;
  - a reviewed translation that is not stale is not changed;
  - with stale translations excluded, stale states are left exactly as they were;
  - a hand-written translation (`missing` state with a value) behaves as in 0.1.
- [ ] Livewire test on the workbench: change the source, correct the target by hand, **Mark as reviewed** → the
      status column shows *Reviewed*; the confirmation mentions the stale count.
- [ ] README editor guide updated; CHANGELOG under `[Unreleased]` (*Fixed*, and *Changed* for the interface).
- [ ] `composer check` green.

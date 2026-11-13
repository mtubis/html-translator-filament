# Show a queued translation without reloading the edit page

GitHub: https://github.com/mtubis/html-translator-filament/issues/1

## Context
With `->queue()`, `TranslateRecordAction` only dispatches `TranslateRecordJob`. When the job finishes, the editor gets
a database notification, but the open edit page still shows the old form state: they have to reload the page and
switch to the target locale to see the draft. Editors do not expect that, and it is easy to save the stale form over
the new translation.

Broadcasting (Reverb, Pusher) would push the result instantly, but it needs a websocket server; many small Filament
apps run on plain hosting. This issue uses short polling, limited to the time a translation is running. Broadcasting
can be an opt-in later.

## Scope
- **Pending marker.** Dispatching a job (record action and bulk action) records "translation running" for the
  record, locale and relations. The job clears it when it finishes or fails for good (`failed()`), not between
  retries. The marker expires on its own (about the `WithoutOverlapping` expiry, 15 min), so a lost worker never
  leaves a page polling forever. It is kept in the application cache; the README says that the `array` store does
  not work across processes.
- **Polling only while needed.** An edit page whose record has a pending marker checks it every few seconds
  (`wire:poll`). Without a marker there is no polling. Interval on the plugin: `->refreshPolling('5s')`, `null`
  turns the feature off. No setup in the user's pages beyond what 0.1 already needs (a page with a public
  `activeLocale`, or the first target locale).
- **When the job is done:**
  - form without unsaved changes: reload the form data for the translated locale in place and show a notification
    ("Draft translation ready");
  - form with unsaved changes: change nothing; show a persistent notification "A draft translation is ready" with a
    **Load** button that reloads the form data (and says that unsaved changes in that locale are discarded);
  - failure: the same "The translation could not be finished" message as the database notification, without a
    reload;
  - relation managers using `RefreshesAfterTranslation` refresh their badges.
- **Button in the database notification.** "Draft translation ready" gets a **Show translation** action that opens
  the record's edit page in the translated locale, for editors who have left the page. The URL is resolved when the
  job is dispatched (from the page's resource), so the job does not need to know the panel. Check how
  `lara-zeus/spatie-translatable` takes the locale from the URL; without a way to pass it, open the edit page and
  document the limitation. Bulk notifications link to the resource's list page.
- README: a "Seeing the result" update in the Queue section, the new plugin option, the cache requirement.

## Out of scope
- Broadcasting / websockets.
- Live progress (units done) while the job runs.

## Acceptance criteria
- [ ] Dispatching sets the pending marker; a finished job and a finally failed job clear it; a retried attempt keeps
      it; it expires without a worker.
- [ ] Livewire tests on the workbench:
  - an edit page with a pending marker renders polling; without one it does not;
  - after the job finishes, a clean form shows the translation in the target locale without a reload;
  - a form with unsaved changes keeps them and shows the **Load** notification; **Load** fills the translation;
  - `->refreshPolling(null)` renders no polling.
- [ ] The "Draft translation ready" database notification has a **Show translation** action pointing to the edit
      page of the record (and the locale when supported).
- [ ] Notifications still contain no prompt text, translations or keys.
- [ ] README updated; CHANGELOG entry under `[Unreleased]`.
- [ ] `composer check` green.

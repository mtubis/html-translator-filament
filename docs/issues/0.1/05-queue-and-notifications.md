# Queue: translation job, overlap protection, rate limiting, database notifications

## Context
A real provider takes seconds per record, so actions must not block the request. `html-translator-laravel` 0.3 will
bring a queued `TranslateHtml` job and events. Until then the plugin has its own job and switches to the package's
job when it ships.

## Scope
- `TranslateRecordJob` (`ShouldQueue`: record, locale, policy, user id): tries/backoff; `WithoutOverlapping` per
  record and locale, so a double click translates once.
- `HtmlTranslatorPlugin::queue(true)`: actions dispatch jobs and notify "translation started"; a Filament
  **database notification** to the user carries the `TranslationReport` when the job finishes. Failures notify with
  a safe message.
- The bulk action uses `Bus::batch()` and sends one notification for the batch.
- `RateLimited` middleware per provider (the limit is configurable), so bulk runs stay under provider limits.
- Docs: the app needs a queue worker and Filament database notifications enabled.

## Acceptance criteria
- [ ] Tests:
  - the actions dispatch jobs with the right arguments (`Queue::fake`);
  - the job writes translations and states and notifies;
  - a provider failure produces a failure notification without secrets;
  - the batch sends one notification;
  - overlapping runs are prevented.
- [ ] `composer check` green.

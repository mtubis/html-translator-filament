# Notes for html-translator-laravel

Friction found while building this plugin on `mtubis/html-translator-laravel` 0.2, as input for its 0.3. Each item
has an upstream issue draft below. Filing it is a separate step.

## 1. The fake can only fail every translation (`failWith()`)
**Seen in:** 0.1 #3, test "one failing attribute does not stop the others".
`HtmlTranslation::fake()->failWith($e)` makes *every* following call throw, so "one call fails, the next ones
succeed" cannot be expressed. **Workaround:** a canned response closure that throws for the matching HTML.

> **Draft issue: Fake: fail only matching translations.** Add
> `failWith(Throwable $e, ?Closure $when = null)` (or `failWhen(Closure(RecordedTranslation): bool, Throwable)`) so
> a test can fail one call out of several. The calls stay recorded as today.

## 2. The fake cannot produce an incomplete result
**Seen in:** 0.1 #3, test "an incomplete result keeps the previous value".
Canned responses are `Closure(string): string`, and the fake always returns a complete `TranslatedDocument`.
**Workaround:** the real `pseudo` driver with `corrupt_every: 1` and `corrupt_retries: true` in a provider entry.
It works, but it couples the test to the corruption mechanics.

> **Draft issue: Fake: canned incomplete results.** Let a canned response return a `TranslatedDocument` (or
> `HtmlTranslation::incomplete(string $html, list<Issue>)`) in addition to a string.

## 3. No public way to read the resolved default provider/profile names
**Seen in:** 0.1 #3, recording `provider`/`profile` on translation states.
When the plugin leaves provider or profile unset, the package default applies, but `TranslatesHtml` offers no getter
for its *name*. The plugin stores `null` ("package default") instead of the name actually used.

> **Draft issue: Expose the provider and profile used.** For example, `TranslatedDocument` metadata
> (provider/profile names in the result) or `TranslatesHtml::defaultProvider(): string` and `defaultProfile(): string`.
> With 0.3's `HtmlTranslated` event carrying them, this may be enough.

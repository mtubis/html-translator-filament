# html-translator for Filament

A Filament v5 plugin for markup-safe AI translation of `spatie/laravel-translatable` models. It is built on
[`mtubis/html-translator-laravel`](https://github.com/mtubis/html-translator-laravel) and adds record and field
actions, a review workflow (`machine` → `reviewed`) and stale-translation detection.

- **Editors:** [Translating in the panel](#translating-in-the-panel-for-editors).
- **Developers:** [Requirements](#requirements), [Installation](#installation),
  [Choosing a provider and a model](#choosing-a-provider-and-a-model), [Queue](#queue), [Command line](#command-line).

> **Status:** 0.1. The public API may still change in 0.x minor versions; the
> [CHANGELOG](CHANGELOG.md) says how. Plans: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Translating in the panel (for editors)
This section is for the people who write and translate content in a Filament panel where the plugin is installed.
Developers: the setup starts at [Requirements](#requirements).

Labels below are the plugin's English ones. The panel shows them in its own language when the plugin has it
([interface languages](#interface-languages)), and an application may rename them.

### The idea
- Content is written in one **source language**. The plugin translates it into the **target languages** with an AI
  model.
- An AI translation is a **draft**. Someone who knows the language reads it, corrects it if needed and marks it
  **reviewed**. The application decides what visitors see; often only reviewed translations.
- The plugin remembers, for every text and language, whether it is translated, a draft or reviewed, and notices when
  the source text changes later.

### Translation states
The **Translation** column of a table shows one badge per target language, e.g. `EN · Machine`. The badge sums up the
whole record (and its related items, if the application counts them): the worst state wins.

| Badge | Meaning | What to do |
|---|---|---|
| **Not translated** (grey) | At least one text has no translation in this language. | Generate a draft, or write the translation yourself. |
| **Machine** (yellow) | Translated by AI, not reviewed yet. | Read it, correct it, mark it as reviewed. |
| **Stale** (red) | The source text changed after it was translated, so the translation may be out of date. | Update the translation (see [When the source text changes](#when-the-source-text-changes)). |
| **Reviewed** (green) | Accepted by a person. | Nothing. |

### Translate one record
1. Open the record for editing and switch the form to the **target language** (the language switcher of the edit
   page). The translation buttons are hidden while you edit the source language.
2. Click **Generate draft translation** at the top of the page.
3. Choose the **Target language** and **What to translate**:
   - **Only what is not translated yet** (default): fills the gaps, keeps every existing translation.
   - **Also replace machine translations**: translates the unreviewed drafts again too, e.g. after the source changed
     or with a better model.
   - **Also replace reviewed translations**: translates everything again, including texts a person has already
     accepted. You have to tick *I understand that reviewed translations will be replaced*.
4. Confirm. What happens next depends on the application:
   - **Right away:** after a moment the form shows the drafts and a notification says how many texts were
     translated, skipped, incomplete or failed.
   - **In the background** (queued): a notification says *Translation started*. When the draft is ready, *Draft
     translation ready* (or *finished with problems*) appears under the bell icon of the panel (it can take up to a
     minute). **Reload the page** to see the draft. (From version 0.3 the page will update by itself.)

If a text could not be translated completely, the notification lists the problems and the old value stays in place.
Try again later, or translate that text yourself.

### Translate one field
Next to a field (in the target language) there is a **Translate** button. It fills only that field with a draft from
the source text and **saves nothing**. Check the text and click **Save**: the field becomes a *Machine* draft.

Use it to redo one text without touching the others, or to update a single stale text.

### Translate many records
In the table, select the records (the checkbox in the header selects the whole page), open the bulk actions and choose
**Generate draft translations**. The form is the same as for one record. In the background mode you get one
notification when all records are done.

### Review a translation
1. Open the record in the target language and read the drafts. Correct what needs correcting and **Save**.
   Saving does **not** review: the state stays *Machine*.
2. Click **Mark as reviewed** at the top of the page (or in the table row, if the application shows it there) and
   confirm.

This marks every draft of the record in that language as reviewed, and also translations you typed yourself, with
a note of who reviewed it and when. Empty texts stay *Not translated*.

### Write a translation yourself
You do not have to use AI. Switch to the target language, type the translation, **Save**, then **Mark as reviewed**.

### When the source text changes
When someone edits the source text, its translations get the **Stale** badge. A reviewed translation stays visible as
it is (it is never removed or downgraded automatically), but it may no longer match the source.

To update it:
1. In the target language, click **Translate** next to the changed field (or run **Generate draft translation** with
   *Also replace machine translations*, or *Also replace reviewed translations* when the old translation was
   reviewed).
2. Check and correct the new draft, **Save**.
3. Click **Mark as reviewed**.

> **Known limitation (0.1):** correcting a stale translation by hand and clicking **Mark as reviewed** does not
> clear the *Stale* badge. Use the **Translate** button of the field first (step 1), then correct the draft.
> To be fixed in 0.2 ([#3](https://github.com/mtubis/html-translator-filament/issues/3)).

### From the command line
Developers or administrators can translate many records at once with
`php artisan html-translator-filament:translate`, see [Command line](#command-line). The results are drafts, exactly
as from the panel: review them as described above. A command for marking many translations as reviewed is planned
for version 0.2.

## Requirements
PHP 8.4+, Laravel 13, Filament 5, `mtubis/html-translator-laravel` ^0.2 (installed with the plugin). The translations
are made by an AI model, so you need an account and an API key for a provider (OpenAI, Anthropic, Gemini) or a local
model server (Ollama, vLLM, LM Studio). See [Choosing a provider and a model](#choosing-a-provider-and-a-model).

## Installation
```bash
composer require mtubis/html-translator-filament
```

Register the plugin on a panel:

```php
use HtmlTranslator\Filament\HtmlTranslatorPlugin;

$panel->plugin(
    HtmlTranslatorPlugin::make()
        ->sourceLocale('es')          // defaults to the first locale
        ->locales(['es', 'en'])       // source + targets
        ->profile('site')             // an entry of config/html-translator.php; null = the package default
        ->provider(null)              // likewise
        ->queue(),                    // translate in queued jobs (0.1 #5)
);
```

## Choosing a provider and a model
The plugin does not talk to AI providers itself: it uses the providers configured for
[`mtubis/html-translator-laravel`](https://github.com/mtubis/html-translator-laravel). Nothing translates until one
of them has a key **and** a model.

1. Publish the configuration file, `config/html-translator.php`:

   ```bash
   php artisan vendor:publish --tag=html-translator-config
   ```

2. Pick a provider and set its key and model in `.env`. `HTML_TRANSLATOR_PROVIDER` names the provider entry used by
   default. **Without it the default is `openai`**, so the actions fail until `OPENAI_API_KEY` and
   `HTML_TRANSLATOR_OPENAI_MODEL` are set.

   ```dotenv
   # Anthropic
   HTML_TRANSLATOR_PROVIDER=anthropic
   ANTHROPIC_API_KEY=sk-ant-...
   HTML_TRANSLATOR_ANTHROPIC_MODEL=claude-sonnet-5-5   # required: there is no default model
   ```

   | Provider | `HTML_TRANSLATOR_PROVIDER` | Key | Model (required) |
   |---|---|---|---|
   | OpenAI | `openai` | `OPENAI_API_KEY` | `HTML_TRANSLATOR_OPENAI_MODEL` |
   | Anthropic | `anthropic` | `ANTHROPIC_API_KEY` | `HTML_TRANSLATOR_ANTHROPIC_MODEL` |
   | Google Gemini | `gemini` | `GEMINI_API_KEY` | `HTML_TRANSLATOR_GEMINI_MODEL` |
   | Ollama (local) | `ollama` | none (`OLLAMA_BASE_URL`, default `http://localhost:11434/v1`) | `HTML_TRANSLATOR_OLLAMA_MODEL` |
   | Pseudo-localization | `pseudo` | none | none |

   The model value is the provider's own model ID, exactly as in its API documentation. Other servers (vLLM, LM
   Studio, any OpenAI-compatible API) and several entries for one provider (a cheap and a strong model) are set up in
   `config/html-translator.php`; see the
   [providers section of html-translator-laravel](https://github.com/mtubis/html-translator-laravel#providers).

3. Check the configuration. It lists every problem at once and never prints a key; `--ping` also sends one short test
   translation per provider:

   ```bash
   php artisan html-translator:check --ping
   ```

4. If configuration is cached (`php artisan config:cache`, usual in production), rebuild the cache, and restart your
   queue workers (`php artisan queue:restart`): a running `queue:work` keeps the configuration it started with.

**Without an API key** (local development, demos, tests) use `HTML_TRANSLATOR_PROVIDER=pseudo`. It needs no network
and no key. It does not really translate: it returns the source text with accented letters (`Servicios` →
`[!! Šéŕṽíçíöš !!]`), so you can try every step of the workflow and see that the markup survives.

To use an entry other than the default only in the panel, name it on the plugin: `->provider('anthropic')`.
Profiles (tone, glossary, terms that must not be translated) are configured the same way, in
`config/html-translator.php`, and picked with `->profile('name')`.

## Translation states
The plugin tracks one state per record, attribute and target locale: `missing`, `machine` or `reviewed`. A
translation is *stale* when its source text changed after it was made. Being stale is derived, never stored, so a
reviewed translation is never downgraded.

1. Publish and run the migration. An existing `translation_states` table with the same schema is left alone.

   ```bash
   php artisan vendor:publish --tag=html-translator-filament-migrations
   php artisan migrate
   ```

2. Let your translatable models track their states:

   ```php
   use HtmlTranslator\Filament\Contracts\TracksTranslations;
   use HtmlTranslator\Filament\Models\Concerns\HasTranslationStates;
   use Spatie\Translatable\HasTranslations;

   final class Post extends Model implements TracksTranslations
   {
       use HasTranslations;
       use HasTranslationStates;

       public array $translatable = ['title', 'body'];
   }
   ```

   Saving a source text creates `missing` states for every target locale of the plugin. Deleting the record removes
   its states. Records removed by a database cascade fire no model events: call
   `TranslationStateStore::deleteFor($morphType, $ids)` for them.

3. Recommended: `Relation::enforceMorphMap([...])`, so the table stores short, stable types instead of class names.

`HtmlTranslator\Filament\Contracts\TranslationStateStore` is the API for reading and writing states. Bind your own
implementation to keep them elsewhere.

## Translating a record
`TranslateRecord` translates the source-locale values of a record, and of the relations you name, into one target
locale. It writes the results and marks their states `machine`:

```php
use HtmlTranslator\Filament\Enums\OverwritePolicy;
use HtmlTranslator\Filament\Translation\TranslateRecord;

$report = app(TranslateRecord::class)($post, 'en', OverwritePolicy::OnlyMissing, relations: ['comments']);

$report->translated(); $report->skipped(); $report->incomplete(); $report->failed();
$report->stats();      // units, batches, input/output tokens, cache hits
$report->outcomes();   // per attribute: status, reason, issues
```

- **Overwrite policies:** `OnlyMissing` (default) translates only what has no translation yet. `OverwriteMachine`
  also replaces machine translations. `Force` also replaces reviewed ones; choose it explicitly.
- **HTML attributes must be declared** on the model: `protected array $htmlTranslatable = ['body'];`. Every other
  translatable attribute is sent as plain text (escaped), so markup in an undeclared attribute would come back as
  text.
- Incomplete or failed attributes keep their previous value and are reported. Model calls happen before the database
  transaction.
- The profile and provider come from the plugin (`->profile()`, `->provider()`); `null` uses the
  html-translator-laravel defaults.

## Filament actions and column
```php
use HtmlTranslator\Filament\Actions\MarkTranslationReviewedAction;
use HtmlTranslator\Filament\Actions\TranslateBulkAction;
use HtmlTranslator\Filament\Actions\TranslateRecordAction;
use HtmlTranslator\Filament\Tables\Columns\TranslationStatusColumn;

// Edit page header
protected function getHeaderActions(): array
{
    return [
        TranslateRecordAction::make()->relations(['comments']),
        MarkTranslationReviewedAction::make()->relations(['comments']),
    ];
}

// Table
$table
    ->modifyQueryUsing(fn ($query) => $query->with('translationStates'))   // one query for every row's badges
    ->columns([TranslationStatusColumn::make()])
    ->recordActions([MarkTranslationReviewedAction::make()])
    ->toolbarActions([TranslateBulkAction::make()]);
```

- **`TranslateRecordAction`** asks for the target locale and the overwrite policy. `Force` needs an explicit
  confirmation. It reports counts, token usage and issues in a notification, and refreshes the form when the page
  edits the translated locale.
- **`MarkTranslationReviewedAction`** marks the edited locale's machine drafts and translations written by hand as
  reviewed, with the current user as reviewer. Saving an edit does not review it: reviewing is the explicit step.
- **`TranslationStatusColumn`** shows one badge per target locale: not translated, machine, stale or reviewed.
- **Active locale:** pages and relation managers with a public `activeLocale` property (like those of
  `lara-zeus/spatie-translatable`) tell the actions which locale is being edited. The actions are hidden while the
  source locale is edited. Without the property, the first target locale is used.
- Notifications contain only counts, usage and the translator's issue messages, never prompt text, translations or
  keys. Labels follow the application locale, see [Interface languages](#interface-languages).

## Field action
Re-translate a single field without touching the others:

```php
RichEditor::make('body')->translateWithAi();                    // macro
TextInput::make('title')->hintAction(TranslateFieldAction::make());  // the same, statically typed
```

- The action fills the field in the edited locale and saves nothing. When the editor saves the form, that attribute
  is marked `machine`, unless its source text changed in the meantime.
- It is hidden while the source locale is edited. Incomplete or failed translations leave the field unchanged and
  show a warning.
- The field name must be the model attribute. HTML vs plain text follows the model's `$htmlTranslatable`.

## Queue
With a real provider a record takes seconds, so translate in the background:

```php
HtmlTranslatorPlugin::make()
    ->locales(['es', 'en'])
    ->queue(connection: 'database', queueName: 'translations')   // both optional
    ->rateLimit(30);                                              // jobs per minute, optional

$panel->databaseNotifications();   // the editor is told when a job (or a bulk batch) is done
```

Without `->queue()` the record and bulk actions translate during the request, and the editor waits for the result.
The field action (`translateWithAi()`) always translates during the request, because it fills the form.

### Running the worker
With `->queue()` the translate action only **dispatches a job**: nothing is translated until a queue worker runs it.
The worker must listen on two queues:

- the plugin's queue: the `queueName` you passed, or the connection's default queue (`default`) when you passed none;
- the connection's default queue, usually `default`: Filament's database notifications ("Draft translation ready",
  "The translation could not be finished") are queued jobs too, and Filament puts them there.

```bash
# The example above (queueName: 'translations'):
php artisan queue:work --queue=translations,default

# Without a queueName both are the default queue:
php artisan queue:work
```

A worker started as plain `php artisan queue:work` (or `queue:listen`) listens only on `default`, so jobs on
`translations` wait in the queue forever and no error is shown. Pass the same connection the plugin uses as the first
argument when it is not your `QUEUE_CONNECTION` (`php artisan queue:work redis --queue=translations,default`).

- **Development:** `php artisan queue:listen --queue=translations,default` reloads the code and configuration for every
  job. Run the worker in the same environment as the application (e.g. `./vendor/bin/sail artisan …` with Sail), so
  it uses the same database, PHP extensions and `.env`.
- **Production:** keep `queue:work` running under a process manager (Supervisor, systemd) and run
  `php artisan queue:restart` after every deployment and every `.env` change.
- **Tables:** the `database` connection needs the `jobs` table; bulk runs use job batches (`job_batches`), and failed
  jobs go to `failed_jobs`. New Laravel applications have all three; otherwise run `php artisan make:queue-table`,
  `php artisan make:queue-batches-table`, `php artisan make:queue-failed-table` and `php artisan migrate`.

### Seeing the result
1. After the click the editor sees "Translation started". The form does not change yet.
2. The worker translates the record (seconds to a minute, depending on the model and the length of the text), saves
   the translation and marks it `machine`.
3. A database notification ("Draft translation ready") appears in the panel's bell. Filament checks for new notifications every 30 seconds
   (`$panel->databaseNotificationsPolling('10s')` to change it).
4. Reload the edit page and switch to the target locale to see the draft.

### Nothing happens after clicking?
| Symptom | Cause | Fix |
|---|---|---|
| No translation, no notification, rows waiting in the `jobs` table | No worker on the plugin's queue | `php artisan queue:work --queue=translations,default` |
| Translation saved, but no notification | The worker does not listen on `default` | Add `default` to `--queue` |
| "The translation could not be finished" notification (after 3 attempts, about a minute) | Provider not configured, or it rejected the request (key, model, quota) | `php artisan html-translator:check --ping`; details in the log and `php artisan queue:failed` |
| The text came back with accented letters | The `pseudo` provider is the default | Set a real provider, see [Choosing a provider and a model](#choosing-a-provider-and-a-model) |
| A new key or model is ignored | The worker or the config cache holds the old configuration | `php artisan config:clear` (or `config:cache`), then `php artisan queue:restart` |

`php artisan queue:monitor database:translations` shows how many jobs are waiting.

### Behaviour
- One record and locale is never translated twice at the same time (`WithoutOverlapping`). Failed jobs notify the
  editor with a generic message; exception details stay in the logs.
- Relation managers that show translations should `use HtmlTranslator\Filament\Concerns\RefreshesAfterTranslation;`,
  so their badges refresh after a synchronous translate action on the page.

## Command line
Translate the records of one or more models into the plugin's target locales:

```bash
php artisan html-translator-filament:translate "App\Models\Post" --dry-run   # what would be translated; no API calls
php artisan html-translator-filament:translate "App\Models\Post"             # translate now, in this process
php artisan html-translator-filament:translate post page --queue              # morph aliases; a batch of queued jobs
```

| Option | Meaning |
|---|---|
| `--locale=en` | Target locale; repeatable. Default: every target locale of the plugin. |
| `--policy=` | `only-missing` (default), `overwrite-machine` or `force`, as in the panel. |
| `--relations=comments` | Relations translated together with each record; repeatable. |
| `--id=12` | Only these records; repeatable. |
| `--dry-run` | Lists what would be translated (`-v`: per record and attribute). Calls no provider, writes nothing, needs no API key. |
| `--queue` | Dispatches one job batch on the plugin's connection and queue and prints the worker command. Nothing is translated until a worker runs it; no panel notifications are sent. |
| `--provider=`, `--profile=` | Another entry of `config/html-translator.php` for this run; synchronous runs only. |
| `--fail-on-incomplete` | Exit code 1 also when a translation is incomplete. |
| `--force` | Required with `--policy=force` when there is no one to answer the confirmation (cron, CI, `--no-interaction`). |

- **Results** are machine drafts (state *Machine*), like translations made in the panel. With `only-missing` a record
  whose texts are all translated costs nothing: no provider call is made for it.
- **Output:** a progress bar per model and locale, a warning for every incomplete or failed attribute (the
  translator's messages with `-v`), and totals: translated, skipped, incomplete, failed, units, tokens and cache hits.
  Source texts, translations and API keys are never printed.
- **Exit code** 1 when an attribute failed, an argument is invalid, or the `force` confirmation is refused; 0
  otherwise.
- **Settings:** locales, provider, profile and queue come from the plugin registered on your panel. With several
  panels, the settings of the last registered one apply.

Translate new and changed content every night (in `routes/console.php`):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('html-translator-filament:translate', ['post', '--no-interaction'])->dailyAt('02:00');
```

## Interface languages
The plugin's buttons, forms, states and notifications follow the application locale (`app()->getLocale()`), like
Filament's own texts. Shipped languages:

| Locale | Language |
|---|---|
| `en` | English |
| `es` | Spanish |
| `de` | German |
| `pl` | Polish |

Planned: French, then Ukrainian and Chinese (Mandarin, `zh_CN`). A locale without a file falls back to English
(Laravel's `fallback_locale`, when it is `en`).

**Change a text or add a language** in your application, without touching the package:

```bash
php artisan lang:publish   # once, if the application has no lang/ directory yet
mkdir -p lang/vendor/html-translator-filament/fr
cp vendor/mtubis/html-translator-filament/lang/en/messages.php lang/vendor/html-translator-filament/fr/messages.php
```

Then translate the values (keep the keys and the `:placeholders`). A file there may also contain only the keys you
want to change. Texts with a count have plural forms separated by `|`: `one|other` in most languages, three forms
in Polish (`1 | 2–4 | 5+`).

New languages for the package itself are welcome as pull requests; a test checks that every language file has the
English keys and placeholders.

## Development
See [CONTRIBUTING.md](CONTRIBUTING.md). `composer check` runs Pint, Rector, PHPStan and PHPUnit. `composer serve`
starts the workbench panel.

## License
MIT, see [LICENSE](LICENSE).

# html-translator for Filament

A Filament v5 plugin for markup-safe AI translation of `spatie/laravel-translatable` models. It is built on
[`mtubis/html-translator-laravel`](https://github.com/mtubis/html-translator-laravel) and adds record and field
actions, a review workflow (`machine` → `reviewed`) and stale-translation detection.

> **Status:** early development, not released. Today the package registers a configurable panel plugin; the actions
> arrive with 0.1 (see [docs/issues/0.1](docs/issues/0.1) and [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)).

## Requirements
PHP 8.4+, Laravel 13, Filament 5, `mtubis/html-translator-laravel` ^0.2 (configured with at least one provider).

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
- **`MarkTranslationReviewedAction`** marks the machine translations of the edited locale as reviewed, with the
  current user as reviewer. Editing a translation by hand does not review it.
- **`TranslationStatusColumn`** shows one badge per target locale: not translated, machine, stale or reviewed.
- **Active locale:** pages and relation managers with a public `activeLocale` property (like those of
  `lara-zeus/spatie-translatable`) tell the actions which locale is being edited. The actions are hidden while the
  source locale is edited. Without the property, the first target locale is used.
- Notifications contain only counts, usage and the translator's issue messages, never prompt text, translations or
  keys. Labels come from `lang/{en,es}`; override them in `lang/vendor/html-translator-filament`.

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

- Run a queue worker (`php artisan queue:work --queue=translations`). Bulk runs use job batches, so they need the
  `job_batches` table.
- One record and locale is never translated twice at the same time (`WithoutOverlapping`). Failed jobs notify the
  editor with a generic message; exception details stay in the logs.
- Relation managers that show translations should `use HtmlTranslator\Filament\Concerns\RefreshesAfterTranslation;`,
  so their badges refresh after a synchronous translate action on the page.

## Development
See [CONTRIBUTING.md](CONTRIBUTING.md). `composer check` runs Pint, Rector, PHPStan and PHPUnit. `composer serve`
starts the workbench panel.

## License
MIT, see [LICENSE](LICENSE).

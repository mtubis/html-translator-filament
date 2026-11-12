# Notes from the dogfooding app

Findings from using the plugin in a real Filament 5 panel (lara-zeus/spatie-translatable pages, RichEditor,
relation managers). Each one becomes an issue for the next release.

## 1. Relation manager badges are stale after the header action (0.1 #4)
`TranslateRecordAction` with `->relations(['items'])` translates the related records, but the relation manager below
the form keeps showing their old status until the page is reloaded.
**Fix idea:** after the action, dispatch Filament's relation-manager refresh event (or `$livewire->dispatch(...)`)
so the managers re-render.

## 2. The status column's default label is the plugin name
`TranslationStatusColumn` uses "HTML translator" / "Traductor HTML" as its header. Editors read it as a tool name.
**Fix:** a dedicated `columns.status` label ("Translation" / "Traducción").

## 3. "Skipped" counts empty sources
The notification reported "Skipped: 7" for a section where most skipped attributes simply had no source text (an
empty lead, an item without an image description). That is noise for editors.
**Fix:** count `empty_source` separately (or not at all) and keep "skipped" for "kept by the overwrite policy".

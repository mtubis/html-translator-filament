<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Concerns;

use Livewire\Attributes\On;

/**
 * Use in relation managers (or any Livewire component) that show translations: they re-render when a translate
 * action on the same page finished, so their status badges are current.
 */
trait RefreshesAfterTranslation
{
    #[On('html-translator-translated')]
    public function refreshAfterTranslation(): void
    {
        // Receiving the event re-renders the component.
    }
}

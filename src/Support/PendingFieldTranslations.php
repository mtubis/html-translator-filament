<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Support;

use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Models\TranslationState;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;

/**
 * Remembers fields filled by the field action until the form is saved (a later request, hence the session): on save
 * the observer marks them `machine`. Without a session (console, queue) nothing is remembered.
 */
final readonly class PendingFieldTranslations
{
    private const string KEY = 'html-translator.pending-fields';

    public function __construct(private Container $container) {}

    public function remember(Model&TracksTranslations $record, string $attribute, string $locale, string $source): void
    {
        $session = $this->session();
        if (! $session instanceof Session) {
            return;
        }

        $session->put(self::KEY . '.' . $this->recordKey($record) . '.' . $attribute . '|' . $locale, TranslationState::hashSource($source));
    }

    /**
     * @return array<string, string> "attribute|locale" => source hash
     */
    public function forRecord(Model&TracksTranslations $record): array
    {
        $pending = $this->session()?->get(self::KEY . '.' . $this->recordKey($record), []);

        $fields = [];
        foreach (\is_array($pending) ? $pending : [] as $field => $hash) {
            if (\is_string($field) && \is_string($hash)) {
                $fields[$field] = $hash;
            }
        }

        return $fields;
    }

    public function forget(Model&TracksTranslations $record): void
    {
        $this->session()?->forget(self::KEY . '.' . $this->recordKey($record));
    }

    private function recordKey(Model $record): string
    {
        $key = $record->getKey();

        // Dots would split the session key; morph types such as "App\Models\Post" contain none, but be safe.
        return str_replace('.', '_', $record->getMorphClass() . ':' . (\is_scalar($key) ? (string) $key : ''));
    }

    private function session(): ?Session
    {
        if (! $this->container->bound('session.store')) {
            return null;
        }

        $session = $this->container->make('session.store');

        return $session instanceof Session ? $session : null;
    }
}

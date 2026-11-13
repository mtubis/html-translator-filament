<?php

declare(strict_types=1);

/*
 * Polish. Reviewed by a native speaker. Neutral wording where possible; Filament's Polish terms ("rekord"). Plural texts have three forms:
 * one | few (2–4, 22–24, …) | many.
 */
return [
    'plugin_name' => 'Tłumacz HTML',
    'status' => [
        'missing' => 'Brak tłumaczenia',
        'machine' => 'Szkic',
        'reviewed' => 'Sprawdzone',
        'stale' => 'Nieaktualne',
    ],
    'actions' => [
        'translate' => [
            'label' => 'Utwórz szkic tłumaczenia',
            'heading' => 'Utwórz szkic na podstawie tekstu źródłowego',
            'description' => 'Automatyczne tłumaczenia to szkice. Sprawdź je przed publikacją.',
            'locale' => 'Język docelowy',
            'policy' => 'Zakres tłumaczenia',
            'policies' => [
                'only_missing' => 'Tylko brakujące tłumaczenia',
                'overwrite_machine' => 'Nadpisz też szkice tłumaczeń',
                'force' => 'Nadpisz też sprawdzone tłumaczenia',
            ],
            'confirm_force' => 'Potwierdzam nadpisanie sprawdzonych tłumaczeń.',
        ],
        'review' => [
            'label' => 'Oznacz jako sprawdzone',
            'heading' => 'Oznaczyć szkice tłumaczeń jako sprawdzone?',
            'done' => 'Oznaczono :count tłumaczenie jako sprawdzone.|Oznaczono :count tłumaczenia jako sprawdzone.|Oznaczono :count tłumaczeń jako sprawdzone.',
            'stale_warning' => 'Nieaktualne tłumaczenia: :count. Tekst źródłowy się zmienił. Przed oznaczeniem tłumaczeń jako sprawdzonych porównaj je z aktualnym tekstem źródłowym.',
            'done_stale' => 'W tym :count tłumaczenie, które było nieaktualne.|W tym :count tłumaczenia, które były nieaktualne.|W tym :count tłumaczeń, które były nieaktualne.',
        ],
        'field' => [
            'label' => 'Przetłumacz',
        ],
        'bulk' => [
            'label' => 'Utwórz szkice tłumaczeń',
        ],
    ],
    'tooltips' => [
        'stale' => 'Tekst źródłowy zmienił się od ostatniego tłumaczenia.',
    ],
    'columns' => [
        'status' => 'Tłumaczenie',
    ],
    'notifications' => [
        'field_translated' => 'Wstawiono szkic tłumaczenia',
        'field_translated_body' => 'Sprawdź szkic i zapisz formularz.',
        'field_not_translated' => 'Nie udało się przetłumaczyć pola',
        'started' => 'Rozpoczęto tłumaczenie',
        'started_body' => 'Gdy szkic będzie gotowy, pojawi się powiadomienie.',
        'failed' => 'Nie udało się dokończyć tłumaczenia',
        'failed_body' => 'Spróbuj ponownie później. Jeśli błąd się powtórzy, skontaktuj się z administratorem panelu.',
        'batch_done' => 'Utworzono szkice tłumaczeń',
        'batch_body' => 'Przetłumaczone rekordy: :done z :total',
        'translated' => 'Utworzono szkic tłumaczenia',
        'translated_with_problems' => 'Podczas tłumaczenia wystąpiły problemy',
        'counts' => 'Przetłumaczone: :translated · Pominięte: :skipped · Niekompletne: :incomplete · Nieudane: :failed',
        'usage' => 'Tokeny: :tokens · Trafienia w pamięci podręcznej: :cache_hits',
        'issues' => 'Problemy:',
    ],
];

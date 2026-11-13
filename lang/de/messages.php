<?php

declare(strict_types=1);

/*
 * German. Reviewed by a fluent speaker; formal "Sie", neutral wording where possible.
 */
return [
    'plugin_name' => 'HTML-Übersetzer',
    'status' => [
        'missing' => 'Nicht übersetzt',
        'machine' => 'Entwurf',
        'reviewed' => 'Geprüft',
        'stale' => 'Nicht aktuell',
    ],
    'actions' => [
        'translate' => [
            'label' => 'Übersetzungsentwurf erstellen',
            'heading' => 'Entwurf aus dem Ausgangstext erstellen',
            'description' => 'Bitte prüfen Sie maschinell erstellte Übersetzungsentwürfe vor der Veröffentlichung.',
            'locale' => 'Zielsprache',
            'policy' => 'Übersetzungsumfang',
            'policies' => [
                'only_missing' => 'Nur fehlende Übersetzungen erstellen',
                'overwrite_machine' => 'Auch Übersetzungsentwürfe überschreiben',
                'force' => 'Auch geprüfte Übersetzungen überschreiben',
            ],
            'confirm_force' => 'Ich bestätige, dass auch geprüfte Übersetzungen überschrieben werden.',
        ],
        'review' => [
            'label' => 'Als geprüft markieren',
            'heading' => 'Übersetzungsentwürfe als geprüft markieren?',
            'done' => ':count Übersetzung als geprüft markiert.|:count Übersetzungen als geprüft markiert.',
            'stale_warning' => ':count Übersetzung ist nicht aktuell, weil sich der Ausgangstext geändert hat. Markieren Sie sie nur dann als geprüft, wenn Sie sie mit dem aktuellen Ausgangstext verglichen haben.|:count Übersetzungen sind nicht aktuell, weil sich der Ausgangstext geändert hat. Markieren Sie sie nur dann als geprüft, wenn Sie sie mit dem aktuellen Ausgangstext verglichen haben.',
            'done_stale' => 'Davon war :count nicht aktuell.|Davon waren :count nicht aktuell.',
        ],
        'field' => [
            'label' => 'Übersetzen',
        ],
        'bulk' => [
            'label' => 'Übersetzungsentwürfe erstellen',
        ],
    ],
    'tooltips' => [
        'stale' => 'Der Ausgangstext wurde seit der letzten Übersetzung geändert.',
    ],
    'columns' => [
        'status' => 'Übersetzung',
    ],
    'notifications' => [
        'field_translated' => 'Übersetzungsentwurf eingefügt',
        'field_translated_body' => 'Prüfen Sie den Entwurf und speichern Sie das Formular.',
        'field_not_translated' => 'Das Feld konnte nicht übersetzt werden',
        'started' => 'Übersetzung gestartet',
        'started_body' => 'Sie werden benachrichtigt, sobald der Entwurf fertig ist.',
        'failed' => 'Die Übersetzung konnte nicht abgeschlossen werden',
        'failed_body' => 'Bitte versuchen Sie es später erneut. Falls der Fehler erneut auftritt, wenden Sie sich an die für dieses System zuständige Person.',
        'batch_done' => 'Übersetzungsentwürfe erstellt',
        'batch_body' => ':done von :total Datensätzen übersetzt.',
        'translated' => 'Übersetzungsentwurf erstellt',
        'translated_with_problems' => 'Beim Übersetzen sind Probleme aufgetreten',
        'counts' => 'Übersetzt: :translated · Übersprungen: :skipped · Unvollständig: :incomplete · Fehlgeschlagen: :failed',
        'usage' => ':tokens Tokens · Cache-Treffer: :cache_hits',
        'issues' => 'Probleme:',
    ],
];

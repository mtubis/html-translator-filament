<?php

declare(strict_types=1);

return [
    'plugin_name' => 'HTML translator',
    'status' => [
        'missing' => 'Not translated',
        'machine' => 'Machine',
        'reviewed' => 'Reviewed',
        'stale' => 'Stale',
    ],
    'actions' => [
        'translate' => [
            'label' => 'Generate draft translation',
            'heading' => 'Generate a draft from the source text',
            'description' => 'Machine translations are drafts: review them before publishing.',
            'locale' => 'Target language',
            'policy' => 'What to translate',
            'policies' => [
                'only_missing' => 'Only what is not translated yet',
                'overwrite_machine' => 'Also replace machine translations',
                'force' => 'Also replace reviewed translations',
            ],
            'confirm_force' => 'I understand that reviewed translations will be replaced',
        ],
        'review' => [
            'label' => 'Mark as reviewed',
            'heading' => 'Mark the machine translations as reviewed?',
            'done' => ':count translation marked as reviewed.|:count translations marked as reviewed.',
        ],
        'field' => [
            'label' => 'Translate',
        ],
        'bulk' => [
            'label' => 'Generate draft translations',
        ],
    ],
    'columns' => [
        'status' => 'Translation',
    ],
    'notifications' => [
        'field_translated' => 'Field translated',
        'field_translated_body' => 'Check the draft and save the form.',
        'field_not_translated' => 'The field could not be translated',
        'started' => 'Translation started',
        'started_body' => 'You will be notified when the draft is ready.',
        'failed' => 'The translation could not be finished',
        'failed_body' => 'Please try again later. If it keeps failing, contact the administrator.',
        'batch_done' => 'Draft translations ready',
        'batch_body' => ':done of :total records translated.',
        'translated' => 'Draft translation ready',
        'translated_with_problems' => 'Draft translation finished with problems',
        'counts' => 'Translated: :translated · Skipped: :skipped · Incomplete: :incomplete · Failed: :failed',
        'usage' => ':tokens tokens, :cache_hits from cache.',
        'issues' => 'Issues:',
    ],
];

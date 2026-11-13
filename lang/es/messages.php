<?php

declare(strict_types=1);

return [
    'plugin_name' => 'Traductor HTML',
    'status' => [
        'missing' => 'Sin traducir',
        'machine' => 'Automática',
        'reviewed' => 'Revisada',
        'stale' => 'Desactualizada',
    ],
    'actions' => [
        'translate' => [
            'label' => 'Generar borrador traducido',
            'heading' => 'Generar un borrador desde el texto original',
            'description' => 'Las traducciones automáticas son borradores: revísalas antes de publicar.',
            'locale' => 'Idioma de destino',
            'policy' => 'Qué traducir',
            'policies' => [
                'only_missing' => 'Solo lo que aún no está traducido',
                'overwrite_machine' => 'Reemplazar también las traducciones automáticas',
                'force' => 'Reemplazar también las revisadas',
            ],
            'confirm_force' => 'Entiendo que se reemplazarán traducciones ya revisadas',
        ],
        'review' => [
            'label' => 'Marcar como revisada',
            'heading' => '¿Marcar las traducciones automáticas como revisadas?',
            'done' => ':count traducción(es) marcada(s) como revisada(s).',
        ],
        'field' => [
            'label' => 'Traducir',
        ],
        'bulk' => [
            'label' => 'Generar borradores traducidos',
        ],
    ],
    'columns' => [
        'status' => 'Traducción',
    ],
    'notifications' => [
        'field_translated' => 'Campo traducido',
        'field_translated_body' => 'Revisa el borrador y guarda el formulario.',
        'field_not_translated' => 'No se pudo traducir el campo',
        'started' => 'Traducción iniciada',
        'started_body' => 'Recibirás un aviso cuando el borrador esté listo.',
        'failed' => 'No se pudo terminar la traducción',
        'failed_body' => 'Inténtalo más tarde. Si el error se repite, avisa al administrador.',
        'batch_done' => 'Borradores traducidos listos',
        'batch_body' => ':done de :total registros traducidos.',
        'translated' => 'Borrador traducido listo',
        'translated_with_problems' => 'Borrador traducido con problemas',
        'counts' => 'Traducidos: :translated · Omitidos: :skipped · Incompletos: :incomplete · Con error: :failed',
        'usage' => ':tokens tokens, :cache_hits desde caché.',
        'issues' => 'Problemas:',
    ],
];

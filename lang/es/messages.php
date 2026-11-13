<?php

declare(strict_types=1);

return [
    'plugin_name' => 'Traductor HTML',
    'status' => [
        'missing' => 'Sin traducir',
        'machine' => 'Borrador',
        'reviewed' => 'Revisada',
        'stale' => 'Desactualizada',
    ],
    'actions' => [
        'translate' => [
            'label' => 'Generar borrador de traducción',
            'heading' => 'Generar un borrador a partir del texto de origen',
            'description' => 'Las traducciones automáticas son borradores: revísalas antes de publicar.',
            'locale' => 'Idioma de destino',
            'policy' => 'Qué traducir',
            'policies' => [
                'only_missing' => 'Solo lo que aún no está traducido',
                'overwrite_machine' => 'Reemplazar también los borradores',
                'force' => 'Reemplazar también las traducciones revisadas',
            ],
            'confirm_force' => 'Entiendo que se reemplazarán traducciones ya revisadas',
        ],
        'review' => [
            'label' => 'Marcar como revisada',
            'heading' => '¿Marcar los borradores como revisados?',
            'done' => ':count traducción marcada como revisada.|:count traducciones marcadas como revisadas.',
            'stale_warning' => 'Hay :count traducción desactualizada debido a cambios en el texto de origen. Compárala con el texto actual antes de marcarla como revisada.|Hay :count traducciones desactualizadas debido a cambios en los textos de origen. Compara cada una con su texto de origen actual antes de marcarla como revisada.',
            'done_stale' => 'De ellas, :count estaba desactualizada.|De ellas, :count estaban desactualizadas.',
        ],
        'field' => [
            'label' => 'Traducir',
        ],
        'bulk' => [
            'label' => 'Generar borradores de traducción',
        ],
    ],
    'tooltips' => [
        'stale' => 'El texto de origen cambió desde la última traducción.',
    ],
    'columns' => [
        'status' => 'Traducción',
    ],
    'notifications' => [
        'field_translated' => 'Se insertó un borrador de traducción',
        'field_translated_body' => 'Revisa el borrador y guarda el formulario.',
        'field_not_translated' => 'No se pudo traducir el campo',
        'started' => 'Traducción iniciada',
        'started_body' => 'Recibirás un aviso cuando el borrador esté listo.',
        'failed' => 'No se pudo terminar la traducción',
        'failed_body' => 'Inténtalo más tarde. Si el error se repite, avisa al administrador.',
        'batch_done' => 'Borradores de traducción generados',
        'batch_body' => 'Registros traducidos: :done de :total.',
        'translated' => 'Borrador de traducción generado',
        'translated_with_problems' => 'Hubo problemas durante la traducción',
        'counts' => 'Traducidos: :translated · Omitidos: :skipped · Incompletos: :incomplete · Con error: :failed',
        'usage' => 'Tokens: :tokens · Aciertos de caché: :cache_hits',
        'issues' => 'Problemas:',
    ],
];

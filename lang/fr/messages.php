<?php

declare(strict_types=1);

/*
 * French. Reviewed by a non-native speaker; corrections by native speakers are welcome. Formal "vous".
 */
return [
    'plugin_name' => 'Traducteur HTML',
    'status' => [
        'missing' => 'Non traduite',
        'machine' => 'Brouillon',
        'reviewed' => 'Vérifiée',
        'stale' => 'Pas à jour',
    ],
    'actions' => [
        'translate' => [
            'label' => 'Générer un brouillon de traduction',
            'heading' => 'Générer un brouillon à partir du texte source',
            'description' => 'Les traductions automatiques sont des brouillons. Vérifiez-les avant la publication.',
            'locale' => 'Langue cible',
            'policy' => 'Étendue de la traduction',
            'policies' => [
                'only_missing' => 'Uniquement les traductions manquantes',
                'overwrite_machine' => 'Remplacer aussi les brouillons',
                'force' => 'Remplacer aussi les traductions vérifiées',
            ],
            'confirm_force' => 'Je confirme le remplacement des traductions vérifiées.',
        ],
        'review' => [
            'label' => 'Marquer comme vérifié',
            'heading' => 'Marquer les brouillons comme vérifiés ?',
            'done' => ':count traduction marquée comme vérifiée.|:count traductions marquées comme vérifiées.',
            'stale_warning' => ':count traduction n’est plus à jour, car le texte source a changé. Comparez-la au texte source actuel avant de la marquer comme vérifiée.|:count traductions ne sont plus à jour. Comparez chacune à son texte source actuel avant de la marquer comme vérifiée.',
            'done_stale' => 'Dont :count qui n’était plus à jour.|Dont :count qui n’étaient plus à jour.',
        ],
        'field' => [
            'label' => 'Traduire',
        ],
        'bulk' => [
            'label' => 'Générer des brouillons de traduction',
        ],
    ],
    'tooltips' => [
        'stale' => 'Le texte source a été modifié depuis la dernière traduction.',
    ],
    'columns' => [
        'status' => 'Traduction',
    ],
    'notifications' => [
        'field_translated' => 'Brouillon de traduction inséré',
        'field_translated_body' => 'Vérifiez le brouillon et enregistrez le formulaire.',
        'field_not_translated' => 'Le champ n’a pas pu être traduit',
        'started' => 'Traduction lancée',
        'started_body' => 'Une notification s’affichera lorsque le brouillon sera prêt.',
        'failed' => 'La traduction n’a pas pu aboutir',
        'failed_body' => 'Réessayez plus tard. Si le problème persiste, contactez votre administrateur.',
        'batch_done' => 'Brouillons de traduction créés',
        'batch_body' => 'Enregistrements traduits : :done sur :total',
        'translated' => 'Brouillon de traduction créé',
        'translated_with_problems' => 'Des problèmes sont survenus lors de la traduction',
        'counts' => 'Traduits : :translated · Ignorés : :skipped · Incomplets : :incomplete · Échecs : :failed',
        'usage' => 'Jetons : :tokens · Résultats réutilisés depuis le cache : :cache_hits',
        'issues' => 'Problèmes :',
    ],
];

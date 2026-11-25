<?php

declare(strict_types=1);

/**
 * Avvisi ai colleghi: nel gestionale (campanella) e per email. Ognuno
 * sceglie, per tipo di avviso, se riceverlo e dove (notification_preferences;
 * senza una riga vale il default del tipo, App\Notifications\NotificationTypes).
 * Le email partono raggruppate da un job (send_notification_emails), mai
 * durante la richiesta di chi ha fatto l'azione.
 *
 * Niente permessi propri: ognuno vede solo i suoi avvisi.
 */
return [
    'name' => 'notifications',
    'label' => 'Avvisi',
    'category' => 'sistema',
    'description' => 'Avvisi ai colleghi nel gestionale e per email, con le preferenze di ciascuno.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'notifications' => [
            'table' => 'notifications',
            'fields' => [
                'user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Per',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                ],
                'type' => [
                    'sql' => 'VARCHAR(40)',
                    'label' => 'Tipo',
                    'base' => true,
                ],
                'title' => [
                    'sql' => 'VARCHAR(200)',
                    'label' => 'Avviso',
                    'base' => true,
                ],
                'body' => [
                    'sql' => 'TEXT',
                    'label' => 'Dettagli',
                    'base' => true,
                    'nullable' => true,
                ],
                'url' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Link',
                    'base' => true,
                    'nullable' => true,
                ],
                'entity_type' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Collegato a',
                    'base' => true,
                    'nullable' => true,
                ],
                'entity_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Id collegato',
                    'base' => true,
                    'nullable' => true,
                    'format' => 'integer',
                ],
                'in_app' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Nella campanella',
                    'base' => true,
                    'format' => 'boolean',
                ],
                'read_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Letto il',
                    'base' => true,
                    'nullable' => true,
                    'format' => 'datetime',
                ],
                'email_due' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Da mandare per email',
                    'base' => true,
                    'nullable' => true,
                    'format' => 'boolean',
                ],
                'emailed_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Email inviata il',
                    'base' => true,
                    'nullable' => true,
                    'format' => 'datetime',
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Avviso', 'area' => 'main', 'fields' => ['title', 'body']],
                        ],
                    ],
                ],
            ],
        ],

        'notification_preferences' => [
            'table' => 'notification_preferences',
            'fields' => [
                'user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Persona',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                ],
                'type' => [
                    'sql' => 'VARCHAR(40)',
                    'label' => 'Tipo di avviso',
                    'base' => true,
                ],
                'in_app' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Nel gestionale',
                    'base' => true,
                    'format' => 'boolean',
                ],
                'email' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Per email',
                    'base' => true,
                    'format' => 'boolean',
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Preferenza', 'area' => 'main', 'fields' => ['type', 'in_app', 'email']],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

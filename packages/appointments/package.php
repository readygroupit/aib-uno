<?php

declare(strict_types=1);

/**
 * Appuntamento/calendario: data/ora inizio-fine, luogo, chi e' coinvolto
 * (operatore + opzionalmente un contatto o un cliente), stato della
 * conferma, promemoria. lead_id/customer_id sono ENTRAMBI opzionali e
 * indipendenti (non e' garantito che un appuntamento riguardi
 * necessariamente uno dei due - puo' essere un impegno interno).
 */
return [
    'name' => 'appointments',
    'label' => 'Appuntamenti',
    'category' => 'operativita',
    'description' => 'Appuntamenti e calendario, con partecipanti e promemoria.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'appointments' => [
            'table' => 'appointments',
            'fields' => [
                'title' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Titolo',
                    'base' => true,
                ],
                'start_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Inizio',
                    'base' => true,
                    'format' => 'datetime',
                ],
                'end_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Fine',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'location' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Luogo',
                    'defaultRequired' => true,
                ],
                'stage' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Stato',
                    'base' => true,
                    'help' => "Vocabolario libero: valori tipici sono 'da confermare', 'confermato', 'completato', 'annullato'.",
                ],
                'assigned_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Operatore',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/users/cerca'],
                ],
                'lead_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Contatto collegato',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'leads', 'table' => 'leads', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/leads/cerca'],
                ],
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente collegato',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'reminder_minutes_before' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Promemoria (minuti prima)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => "Quanti minuti prima dell'inizio avvisare - il vero invio del promemoria non e' ancora implementato, per ora e' solo un dato.",
                ],
                'notes' => [
                    'sql' => 'TEXT',
                    'label' => 'Note',
                    'defaultRequired' => true,
                ],
            ],

            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            [
                                'title' => 'Appuntamento',
                                'area' => 'main',
                                'fields' => ['title', 'start_at', 'end_at', 'location', 'stage', 'notes'],
                            ],
                            [
                                'title' => 'Collegamenti',
                                'area' => 'sidebar',
                                'fields' => ['assigned_user_id', 'lead_id', 'customer_id', 'reminder_minutes_before'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

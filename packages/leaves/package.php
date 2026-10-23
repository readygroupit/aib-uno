<?php

declare(strict_types=1);

/**
 * Ferie, permessi e assenze: chi le chiede, chi le approva (permesso
 * leaves.approve), e l'organizer che non pianifica lavoro a chi e' via
 * (giornata intera) o toglie le ore del permesso.
 */
return [
    'name' => 'leaves',
    'label' => 'Ferie e permessi',
    'category' => 'operativita',
    'description' => 'Richieste di ferie, permessi e ROL con approvazione; l\'organizer ne tiene conto.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'permissions' => [
        'view' => ['Chiedere ferie e permessi', 'Chiedere le proprie ferie e i propri permessi e vederli.'],
        'approve' => ['Approvare ferie e permessi', 'Vedere quelle di tutti, approvarle o rifiutarle.'],
    ],
    'entities' => [
        'leave_requests' => [
            'table' => 'leave_requests',
            'fields' => [
                'user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Persona',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
                'kind' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Cosa',
                    'base' => true,
                    'options' => ['ferie', 'permesso', 'ROL', 'malattia', 'altro'],
                ],
                'start_date' => [
                    'sql' => 'DATE',
                    'label' => 'Dal',
                    'base' => true,
                    'format' => 'date',
                ],
                'end_date' => [
                    'sql' => 'DATE',
                    'label' => 'Al',
                    'defaultRequired' => true,
                    'format' => 'date',
                    'help' => 'Vuoto: un giorno solo.',
                ],
                'start_time' => [
                    'sql' => 'VARCHAR(5)',
                    'label' => 'Dalle',
                    'defaultRequired' => true,
                    'format' => 'time',
                    'showIf' => ['field' => 'kind', 'in' => ['permesso', 'ROL']],
                    'help' => 'Vuoto: tutta la giornata.',
                ],
                'end_time' => [
                    'sql' => 'VARCHAR(5)',
                    'label' => 'Alle',
                    'defaultRequired' => true,
                    'format' => 'time',
                    'showIf' => ['field' => 'kind', 'in' => ['permesso', 'ROL']],
                ],
                'stage' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['richiesta', 'approvata', 'rifiutata', 'annullata'],
                ],
                'note' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Note',
                    'defaultRequired' => true,
                ],
                'decided_by' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Deciso da',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                ],
                'decided_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Deciso il',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'reply' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Risposta',
                    'defaultRequired' => true,
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Cosa e quando', 'area' => 'main', 'fields' => ['user_id', 'kind', 'start_date', 'end_date', 'start_time', 'end_time', 'note']],
                            ['title' => 'Stato', 'area' => 'sidebar', 'fields' => ['stage', 'reply']],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

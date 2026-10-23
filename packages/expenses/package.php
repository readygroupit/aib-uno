<?php

declare(strict_types=1);

/**
 * Spese: benzina, parcheggi, pedaggi, modelle, materiali di uno shooting o
 * di un lavoro. Chi spende la segna (anche dal telefono), i titolari la
 * approvano (permesso expenses.approve), l'amministrazione la rimborsa.
 * Collegata al cliente e alla commessa, finisce nel costo della commessa.
 */
return [
    'name' => 'expenses',
    'label' => 'Spese',
    'category' => 'vendite',
    'description' => 'Spese dei lavori (trasferte, shooting, materiali) con approvazione e rimborso, collegate a cliente e commessa.',
    'dependsOn' => ['customers'],
    'selectableDirectly' => true,
    'permissions' => [
        'view' => ['Segnare le spese', 'Segnare le proprie spese e vederle.'],
        'approve' => ['Approvare e rimborsare le spese', 'Vedere le spese di tutti, approvarle, rifiutarle, segnarle rimborsate.'],
    ],
    'entities' => [
        'expenses' => [
            'table' => 'expenses',
            'fields' => [
                'user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Chi ha speso',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
                'expense_date' => [
                    'sql' => 'DATE',
                    'label' => 'Quando',
                    'base' => true,
                    'format' => 'date',
                ],
                'kind' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Tipo',
                    'base' => true,
                    'options' => ['carburante', 'parcheggio', 'pedaggio', 'treno o aereo', 'pasto', 'modelle', 'materiali', 'altro'],
                ],
                'amount' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Importo',
                    'base' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                ],
                'description' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Per cosa',
                    'defaultRequired' => true,
                    'help' => "Es. 'shooting a Sorrento, andata e ritorno'.",
                ],
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'work_order_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Commessa',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'autocomplete' => ['source' => '/riferimenti/work_orders/cerca'],
                ],
                'paid_by' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Pagata con',
                    'defaultRequired' => true,
                    'options' => ['soldi miei', 'carta aziendale', 'contanti aziendali'],
                    'help' => 'Con i propri soldi va rimborsata.',
                ],
                'rebill' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Da riaddebitare al cliente',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                ],
                'receipt_link' => [
                    'sql' => 'VARCHAR(500)',
                    'label' => 'Scontrino o ricevuta',
                    'defaultRequired' => true,
                    'help' => 'Link alla foto dello scontrino (Drive).',
                ],
                'stage' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['da approvare', 'approvata', 'rifiutata', 'rimborsata'],
                ],
                'decided_by' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Decisa da',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                ],
                'decided_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Decisa il',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'reimbursed_at' => [
                    'sql' => 'DATE',
                    'label' => 'Rimborsata il',
                    'defaultRequired' => true,
                    'format' => 'date',
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'La spesa', 'area' => 'main', 'fields' => ['kind', 'amount', 'expense_date', 'description', 'receipt_link']],
                            ['title' => 'Per chi', 'area' => 'sidebar', 'fields' => ['customer_id', 'work_order_id', 'rebill']],
                            ['title' => 'Chi', 'area' => 'sidebar', 'fields' => ['user_id', 'paid_by', 'stage']],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

<?php

declare(strict_types=1);

/**
 * Commesse: il lavoro da consegnare a un cliente (un sito, uno shooting,
 * una campagna...), con le sue fasi. Le fasi sono attivita' del pacchetto
 * 'tasks' collegate alla commessa (entity_type 'work_orders'): cosi' chi
 * pianifica la giornata trova in un posto solo le fasi delle commesse e le
 * attivita' sciolte.
 *
 * I tipi di commessa sono modelli: ogni tipo ha le sue fasi gia' pronte
 * (con il giorno previsto rispetto all'inizio, il tempo, se aspettano il
 * cliente, una checklist). Creando una commessa di quel tipo le fasi
 * diventano attivita' (App\Work\WorkOrderService).
 *
 * Lo stato "in attesa del cliente" lo mette il sistema quando tocca a una
 * fase che aspetta il cliente, e lo toglie quando quella fase e' fatta;
 * last_activity_at serve a vedere le commesse ferme.
 */
return [
    'name' => 'work_orders',
    'label' => 'Commesse',
    'category' => 'operativita',
    'description' => 'Commesse per i clienti, con fasi a modello, attese del cliente, scadenze e tempi.',
    'dependsOn' => ['customers', 'tasks'],
    'selectableDirectly' => true,
    'entities' => [
        'work_order_types' => [
            'table' => 'work_order_types',
            'fields' => [
                'name' => [
                    'sql' => 'VARCHAR(150)',
                    'label' => 'Nome',
                    'base' => true,
                    'help' => "Es. 'Sito vetrina', 'Shooting fotografico', 'Campagna ADV'.",
                ],
                'description' => [
                    'sql' => 'TEXT',
                    'label' => 'Descrizione',
                    'defaultRequired' => true,
                ],
                'default_days' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Durata tipica (giorni)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => "Serve a proporre la data di consegna.",
                ],
                'stage' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['in uso', 'non in uso'],
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Tipo di commessa', 'area' => 'main', 'fields' => ['name', 'description']],
                            ['title' => 'Uso', 'area' => 'sidebar', 'fields' => ['default_days', 'stage']],
                        ],
                    ],
                ],
            ],
        ],

        'work_order_type_steps' => [
            'table' => 'work_order_type_steps',
            'fields' => [
                'work_order_type_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Tipo di commessa',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'work_order_types', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/work_order_types/cerca'],
                ],
                'position' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Ordine',
                    'base' => true,
                    'format' => 'integer',
                ],
                'title' => [
                    'sql' => 'VARCHAR(200)',
                    'label' => 'Fase',
                    'base' => true,
                ],
                'description' => [
                    'sql' => 'TEXT',
                    'label' => 'Cosa si fa',
                    'defaultRequired' => true,
                ],
                'offset_days' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Giorno previsto (dall\'inizio)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => "0 = il giorno di inizio della commessa.",
                ],
                'estimated_minutes' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Tempo previsto (minuti)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'waits_for_client' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Aspetta il cliente',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                    'help' => "Testi, foto, un'approvazione: la commessa passa da sola 'in attesa del cliente'.",
                ],
                'checklist' => [
                    'sql' => 'TEXT',
                    'label' => 'Checklist',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                    'help' => 'Un punto per riga.',
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Fase', 'area' => 'main', 'fields' => ['work_order_type_id', 'title', 'description', 'checklist']],
                            ['title' => 'Quando e quanto', 'area' => 'sidebar', 'fields' => ['position', 'offset_days', 'estimated_minutes', 'waits_for_client']],
                        ],
                    ],
                ],
            ],
        ],

        'work_orders' => [
            'table' => 'work_orders',
            'fields' => [
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'work_order_type_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Tipo di commessa',
                    'base' => true,
                    'nullable' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'work_order_types', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/work_order_types/cerca'],
                    'help' => "Le sue fasi diventano le attivita' della commessa. Vuoto: una commessa su misura, le fasi le aggiungi tu.",
                ],
                'customer_service_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Servizio venduto',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customer_services', 'table' => 'customer_services', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customer_services/cerca'],
                    'help' => 'Il servizio venduto da cui nasce, se c\'e\'.',
                ],
                'title' => [
                    'sql' => 'VARCHAR(200)',
                    'label' => 'Titolo',
                    'base' => true,
                    'help' => "Es. 'Nuovo sito della trattoria'.",
                ],
                'stage' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['da avviare', 'in corso', 'in attesa del cliente', 'in pausa', 'consegnata', 'annullata'],
                ],
                'priority' => [
                    'sql' => 'VARCHAR(10)',
                    'label' => 'Priorita',
                    'defaultRequired' => true,
                    'options' => ['alta', 'normale', 'bassa'],
                ],
                'owner_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Responsabile',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
                'supplier_customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Fornitore esterno',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'start_date' => [
                    'sql' => 'DATE',
                    'label' => 'Inizio',
                    'base' => true,
                    'format' => 'date',
                ],
                'due_date' => [
                    'sql' => 'DATE',
                    'label' => 'Consegna prevista',
                    'defaultRequired' => true,
                    'format' => 'date',
                    'help' => 'Vuota: si calcola dalla durata tipica del tipo di commessa.',
                ],
                'delivered_at' => [
                    'sql' => 'DATE',
                    'label' => 'Consegnata il',
                    'defaultRequired' => true,
                    'format' => 'date',
                    'showIf' => ['field' => 'stage', 'in' => ['consegnata']],
                ],
                'waiting_reason' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Cosa aspettiamo dal cliente',
                    'defaultRequired' => true,
                    'showIf' => ['field' => 'stage', 'in' => ['in attesa del cliente']],
                ],
                'waiting_since' => [
                    'sql' => 'DATE',
                    'label' => 'In attesa dal',
                    'defaultRequired' => true,
                    'format' => 'date',
                    'showIf' => ['field' => 'stage', 'in' => ['in attesa del cliente']],
                ],
                'last_reminder_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Ultimo sollecito',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'last_activity_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Ultimo movimento',
                    'defaultRequired' => true,
                    'format' => 'datetime',
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
                            ['title' => 'Commessa', 'area' => 'main', 'fields' => ['customer_id', 'work_order_type_id', 'title', 'customer_service_id']],
                            ['title' => 'Tempi', 'area' => 'main', 'fields' => ['start_date', 'due_date', 'delivered_at']],
                            ['title' => 'Attesa del cliente', 'area' => 'main', 'fields' => ['waiting_reason', 'waiting_since']],
                            ['title' => 'Chi la segue', 'area' => 'sidebar', 'fields' => ['owner_user_id', 'supplier_customer_id', 'stage', 'priority']],
                            ['title' => 'Note', 'area' => 'main', 'fields' => ['notes']],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

<?php

declare(strict_types=1);

/**
 * Richieste dei clienti: quello che arriva al telefono, su WhatsApp o per
 * email e oggi interrompe chi sta lavorando.
 *
 * Si segna in pochi secondi (chi ha risposto, cliente, cosa chiede), va a
 * chi deve farla (proposto dalla squadra del cliente) che se la trova
 * nell'organizer come attivita', invece di sentirsela chiedere ad alta
 * voce. Le non assegnate le smista chi coordina (permesso
 * client_requests.assign). Un lavoro extra si segna come tale e, fatto, va
 * nello scadenzario con un clic: e' l'Excel dei "lavori extra".
 */
return [
    'name' => 'client_requests',
    'label' => 'Richieste dei clienti',
    'category' => 'crm',
    'description' => 'Richieste dei clienti da telefono, WhatsApp o email: si segnano, si assegnano, diventano attivita\'; i lavori extra vanno nello scadenzario.',
    'dependsOn' => ['customers', 'tasks'],
    'selectableDirectly' => true,
    'permissions' => [
        'view' => ['Vedere le richieste', 'Le richieste dei clienti e a chi sono assegnate.'],
        'create' => ['Segnare le richieste', 'Registrare una richiesta arrivata da un cliente.'],
        'edit' => ['Lavorare le richieste', 'Prenderle in carico, chiuderle, modificarle.'],
        'delete' => ['Eliminare le richieste', 'Eliminare richieste segnate per errore.'],
        'assign' => ['Smistare le richieste', 'Assegnare le richieste nuove: le vede anche nell\'organizer.'],
    ],
    'entities' => [
        'client_requests' => [
            'table' => 'client_requests',
            'fields' => [
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'summary' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Cosa chiede',
                    'base' => true,
                    'help' => "In una riga, es. 'cambiare il prezzo del menu sul sito'.",
                ],
                'details' => [
                    'sql' => 'TEXT',
                    'label' => 'Dettagli',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                ],
                'kind' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Tipo',
                    'defaultRequired' => true,
                    'options' => ['modifica', 'nuovo contenuto', 'assistenza tecnica', 'amministrativa', 'informazioni', 'altro'],
                ],
                'channel' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Arrivata da',
                    'base' => true,
                    'options' => ['telefono', 'whatsapp', 'email', 'di persona'],
                ],
                'received_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Quando',
                    'base' => true,
                    'format' => 'datetime',
                ],
                'received_by_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Chi l\'ha ricevuta',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
                'assigned_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Chi la fa',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                    'help' => 'Vuoto: la propone la squadra del cliente, o la smista chi coordina.',
                ],
                'stage' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['nuova', 'presa in carico', 'fatta', 'annullata'],
                ],
                'priority' => [
                    'sql' => 'VARCHAR(10)',
                    'label' => 'Priorita',
                    'defaultRequired' => true,
                    'options' => ['alta', 'normale', 'bassa'],
                ],
                'due_date' => [
                    'sql' => 'DATE',
                    'label' => 'Entro il',
                    'defaultRequired' => true,
                    'format' => 'date',
                ],
                'estimated_minutes' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Tempo previsto (minuti)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'is_extra' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Lavoro extra da fatturare',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                ],
                'extra_amount' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Importo extra (+ IVA)',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                    'showIf' => ['field' => 'is_extra', 'in' => ['1']],
                ],
                'installment_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Incasso',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'task_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => "Attivita'",
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'resolution' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => "Com'e' finita",
                    'defaultRequired' => true,
                ],
                'done_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Fatta il',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'La richiesta', 'area' => 'main', 'fields' => ['customer_id', 'summary', 'details', 'kind']],
                            ['title' => 'Chi e quando', 'area' => 'sidebar', 'fields' => ['assigned_user_id', 'due_date', 'priority', 'estimated_minutes', 'stage']],
                            ['title' => 'Da dove', 'area' => 'sidebar', 'fields' => ['channel', 'received_at', 'received_by_user_id']],
                            ['title' => 'Extra', 'area' => 'main', 'fields' => ['is_extra', 'extra_amount', 'resolution']],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

<?php

declare(strict_types=1);

/**
 * Preventivi e contratti.
 *
 * Un preventivo e' fatto di righe (dal listino o scritte a mano), una
 * tantum o ricorrenti, ognuna con il suo piano di pagamento. Lo approva il
 * titolare (permesso quotes.approve) prima che parta; il cliente lo
 * accetta dalla sua area (o lo segna l'agenzia). Accettato, ogni riga
 * diventa un servizio venduto con le sue scadenze: niente da ricopiare,
 * niente dati persi tra preventivo e contratto (l'errore piu' frequente).
 *
 * Le condizioni (tempi, giri di modifiche inclusi, pagamento) stanno nel
 * preventivo: accettato, e' il contratto.
 */
return [
    'name' => 'quotes',
    'label' => 'Preventivi',
    'category' => 'vendite',
    'description' => 'Preventivi dal listino con approvazione del titolare, accettazione del cliente dalla sua area e passaggio automatico a servizi venduti e scadenzario.',
    'dependsOn' => ['customers', 'services', 'customer_services', 'installments'],
    'selectableDirectly' => true,
    'permissions' => [
        'view' => ['Visualizzare preventivi', "Vedere l'elenco e aprire il dettaglio."],
        'create' => ['Creare preventivi', 'Preparare preventivi nuovi.'],
        'edit' => ['Modificare preventivi', 'Modificare preventivi e righe, segnarli inviati.'],
        'delete' => ['Eliminare preventivi', 'Eliminare preventivi.'],
        'approve' => ['Approvare preventivi', 'Approvare i preventivi prima che partano.'],
    ],
    'entities' => [
        'quotes' => [
            'table' => 'quotes',
            'fields' => [
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                    'help' => 'Anche un potenziale cliente: si aggiunge ai clienti.',
                ],
                'title' => [
                    'sql' => 'VARCHAR(200)',
                    'label' => 'Oggetto',
                    'base' => true,
                    'help' => "Es. 'Nuovo sito e gestione social 2027'.",
                ],
                'quote_number' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Numero',
                    'defaultRequired' => true,
                ],
                'number_year' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Anno',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'number_seq' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Progressivo',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'quote_date' => [
                    'sql' => 'DATE',
                    'label' => 'Data',
                    'base' => true,
                    'format' => 'date',
                ],
                'valid_until' => [
                    'sql' => 'DATE',
                    'label' => 'Valido fino al',
                    'defaultRequired' => true,
                    'format' => 'date',
                ],
                'stage' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['bozza', 'da approvare', 'approvato', 'inviato', 'accettato', 'rifiutato', 'scaduto'],
                ],
                'owner_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Chi lo segue',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
                'intro' => [
                    'sql' => 'TEXT',
                    'label' => 'Premessa',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                    'help' => 'Cosa ha chiesto il cliente e cosa proponete, in due righe.',
                ],
                'conditions' => [
                    'sql' => 'TEXT',
                    'label' => 'Condizioni',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                    'help' => 'Tempi, cosa serve dal cliente, cosa non e\' compreso.',
                ],
                'payment_terms' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Pagamento',
                    'defaultRequired' => true,
                    'help' => "Es. 'bonifico a 30 giorni', 'addebito SEPA mensile'.",
                ],
                'included_revisions' => [
                    'sql' => 'TINYINT UNSIGNED',
                    'label' => 'Giri di modifiche inclusi',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => 'Oltre, le modifiche sono un extra.',
                ],
                'discount_percent' => [
                    'sql' => 'DECIMAL(5,2)',
                    'label' => 'Sconto (%)',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                ],
                'total_one_off' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Totale una tantum',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                ],
                'total_yearly' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Totale ricorrente all\'anno',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                ],
                'approved_by' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Approvato da',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                ],
                'approved_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Approvato il',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'sent_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Inviato il',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'accepted_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Accettato il',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'accepted_via' => [
                    'sql' => 'VARCHAR(60)',
                    'label' => 'Come ha accettato',
                    'defaultRequired' => true,
                ],
                'rejected_reason' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Perche\' no',
                    'defaultRequired' => true,
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Preventivo', 'area' => 'main', 'fields' => ['customer_id', 'title', 'intro', 'conditions']],
                            ['title' => 'Date e pagamento', 'area' => 'sidebar', 'fields' => ['quote_date', 'valid_until', 'payment_terms', 'included_revisions', 'discount_percent', 'owner_user_id']],
                        ],
                    ],
                ],
            ],
        ],
        'quote_lines' => [
            'table' => 'quote_lines',
            'fields' => [
                'quote_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Preventivo',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'quotes', 'column' => 'id'],
                ],
                'service_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Dal listino',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'services', 'table' => 'services', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/services/cerca'],
                ],
                'description' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Descrizione',
                    'base' => true,
                ],
                'details' => [
                    'sql' => 'TEXT',
                    'label' => 'Cosa comprende',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                ],
                'quantity' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Quantita\'',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                ],
                'unit_price' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Prezzo',
                    'base' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                    'help' => 'Per un servizio ricorrente: il prezzo di ogni periodo (mese, anno...).',
                ],
                'billing_type' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Tipo',
                    'defaultRequired' => true,
                    'options' => ['una tantum', 'ricorrente'],
                ],
                'recurrence' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Ogni quanto',
                    'defaultRequired' => true,
                    'options' => ['mensile', 'trimestrale', 'semestrale', 'annuale'],
                    'showIf' => ['field' => 'billing_type', 'in' => ['ricorrente']],
                ],
                'payment_plan' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Piano di pagamento',
                    'defaultRequired' => true,
                    'options' => ['unica soluzione', 'acconto e saldo', 'rate'],
                ],
                'deposit_percent' => [
                    'sql' => 'DECIMAL(5,2)',
                    'label' => 'Acconto (%)',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                    'showIf' => ['field' => 'payment_plan', 'in' => ['acconto e saldo']],
                ],
                'installments_count' => [
                    'sql' => 'TINYINT UNSIGNED',
                    'label' => 'Numero di rate',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'showIf' => ['field' => 'payment_plan', 'in' => ['rate']],
                ],
                'start_date' => [
                    'sql' => 'DATE',
                    'label' => 'Inizio previsto',
                    'defaultRequired' => true,
                    'format' => 'date',
                    'help' => 'Vuoto: dal giorno dell\'accettazione.',
                ],
                'position' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Ordine',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'customer_service_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Servizio venduto',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Riga', 'area' => 'main', 'fields' => ['quote_id', 'service_id', 'description', 'details']],
                            ['title' => 'Prezzo', 'area' => 'sidebar', 'fields' => ['quantity', 'unit_price', 'billing_type', 'recurrence']],
                            ['title' => 'Pagamento', 'area' => 'sidebar', 'fields' => ['payment_plan', 'deposit_percent', 'installments_count', 'start_date']],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

<?php

declare(strict_types=1);

/**
 * Un servizio venduto a un cliente: il "contratto" con prezzo, scadenza e
 * piano di pagamento propri. Lo stesso cliente puo' avere l'e-commerce che
 * si rinnova a settembre e lo shooting a gennaio: ogni riga ha la sua data.
 *
 * Le rate e i rinnovi non sono campi qui ma righe di 'installments'
 * (App\Billing\PaymentPlanService le genera dal piano scelto): una riga
 * per ogni incasso atteso, ognuna con il suo stato.
 *
 * price e' l'importo di UN periodo (un mese per un mensile, un anno per un
 * annuale, il totale per una tantum): il piano di pagamento lo divide.
 */
return [
    'name' => 'customer_services',
    'label' => 'Servizi dei clienti',
    'category' => 'vendite',
    'description' => 'Servizi venduti ai clienti, con scadenze, rinnovi e piano di pagamento.',
    'dependsOn' => ['customers', 'services'],
    'selectableDirectly' => true,
    'entities' => [
        'customer_services' => [
            'table' => 'customer_services',
            'fields' => [
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'service_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Servizio del listino',
                    'base' => true,
                    'nullable' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'services', 'table' => 'services', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/services/cerca'],
                    'help' => 'Scegliendolo si compilano prezzo e rinnovo dal listino. Si puo\' lasciare vuoto per un lavoro su misura.',
                ],
                'title' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Descrizione',
                    'base' => true,
                    'help' => "Es. 'E-commerce negozio online' oppure 'Shooting collezione primavera'.",
                ],
                'reference' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Riferimento',
                    'defaultRequired' => true,
                    'help' => "Cosa identifica il servizio: il dominio (www.cliente.it), l'account social, il numero di contratto.",
                ],
                'stage' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['attivo', 'sospeso', 'disdetto', 'concluso'],
                ],
                'billing_type' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Come si paga',
                    'base' => true,
                    'options' => ['una tantum', 'ricorrente'],
                ],
                'recurrence' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Ogni quanto si rinnova',
                    'defaultRequired' => true,
                    'options' => ['mensile', 'trimestrale', 'semestrale', 'annuale'],
                    'showIf' => ['field' => 'billing_type', 'in' => ['ricorrente']],
                ],
                'price' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Importo',
                    'base' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                    'help' => 'Per un servizio ricorrente: quanto costa ogni periodo (un mese, un anno...).',
                ],
                'cost_price' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Costo per voi',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                ],
                'start_date' => [
                    'sql' => 'DATE',
                    'label' => 'Inizio',
                    'base' => true,
                    'format' => 'date',
                ],
                'renewal_date' => [
                    'sql' => 'DATE',
                    'label' => 'Prossimo rinnovo',
                    'defaultRequired' => true,
                    'format' => 'date',
                    'showIf' => ['field' => 'billing_type', 'in' => ['ricorrente']],
                    'help' => 'Se lo lasci vuoto si calcola dalla data di inizio.',
                ],
                'end_date' => [
                    'sql' => 'DATE',
                    'label' => 'Fine',
                    'defaultRequired' => true,
                    'format' => 'date',
                    'showIf' => ['field' => 'stage', 'in' => ['disdetto', 'concluso']],
                ],
                'auto_renew' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Rinnovo automatico',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                    'showIf' => ['field' => 'billing_type', 'in' => ['ricorrente']],
                    'help' => 'Alla scadenza si rinnova da solo, dopo il preavviso al cliente.',
                ],
                'notice_days' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Preavviso (giorni)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'showIf' => ['field' => 'billing_type', 'in' => ['ricorrente']],
                    'help' => 'Quanti giorni prima del rinnovo avvisare il cliente.',
                ],
                'payment_plan' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Piano di pagamento',
                    'base' => true,
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
                'balance_date' => [
                    'sql' => 'DATE',
                    'label' => 'Data del saldo',
                    'defaultRequired' => true,
                    'format' => 'date',
                    'showIf' => ['field' => 'payment_plan', 'in' => ['acconto e saldo']],
                    'help' => 'Se la lasci vuota: un mese dopo l\'inizio.',
                ],
                'installments_count' => [
                    'sql' => 'TINYINT UNSIGNED',
                    'label' => 'Numero di rate',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'showIf' => ['field' => 'payment_plan', 'in' => ['rate']],
                    'help' => 'Una rata al mese a partire dalla data di inizio.',
                ],
                'payment_method' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Metodo di pagamento',
                    'defaultRequired' => true,
                    'options' => ['bonifico', 'carta', 'addebito SEPA', 'PayPal', 'contanti'],
                ],
                'supplier_customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Fornitore',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'notes' => [
                    'sql' => 'TEXT',
                    'label' => 'Note',
                    'defaultRequired' => true,
                ],
                // L'esito del servizio, aggiornato da chi lo segue: entra nella
                // temperatura del cliente (un servizio che non rende e' un
                // rinnovo a rischio).
                'results' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Sta dando risultati?',
                    'defaultRequired' => true,
                    'options' => ['buoni', 'parziali', 'scarsi', 'da valutare'],
                ],
                'results_note' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Come va',
                    'defaultRequired' => true,
                    'help' => "Es. 'follower +20% in 3 mesi', 'poche richieste dal sito'.",
                ],
                'results_updated_at' => [
                    'sql' => 'DATE',
                    'label' => 'Esito aggiornato il',
                    'defaultRequired' => true,
                    'format' => 'date',
                ],
            ],

            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            [
                                'title' => 'Servizio',
                                'area' => 'main',
                                'fields' => ['customer_id', 'service_id', 'title', 'reference', 'stage', 'end_date'],
                            ],
                            [
                                'title' => 'Durata e rinnovo',
                                'area' => 'main',
                                'fields' => ['billing_type', 'recurrence', 'start_date', 'renewal_date', 'auto_renew', 'notice_days'],
                            ],
                            [
                                'title' => 'Importi',
                                'area' => 'sidebar',
                                'fields' => ['price', 'cost_price', 'supplier_customer_id'],
                            ],
                            [
                                'title' => 'Pagamento',
                                'area' => 'sidebar',
                                'fields' => ['payment_plan', 'deposit_percent', 'balance_date', 'installments_count', 'payment_method'],
                            ],
                            [
                                'title' => 'Risultati',
                                'area' => 'main',
                                'fields' => ['results', 'results_note'],
                            ],
                            [
                                'title' => 'Note',
                                'area' => 'main',
                                'fields' => ['notes'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

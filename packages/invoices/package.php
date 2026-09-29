<?php

declare(strict_types=1);

/**
 * Fattura base - solo la testata (numero, cliente, date, importi,
 * stato, metodo di pagamento), NIENTE righe di dettaglio prodotto/
 * servizio (una vera fattura articolata servirebbe una entity a parte
 * "righe fattura", non costruita qui per tempo) e NESSUNA generazione
 * PDF: richiederebbe una libreria esterna, e il framework e' pensato
 * zero-dipendenze (vedi la memoria di progetto) - da decidere insieme
 * prima di aggiungerla. Istrumed stesso (vedi la ricerca fatta) non ha
 * una tabella fatture propria: delega tutto alla libreria di
 * fatturazione di Core (Aruba/Fattura24) - qui invece serve un dato
 * generico anche senza quell'integrazione, per questo esiste comunque.
 */
return [
    'name' => 'invoices',
    'label' => 'Fatture',
    'category' => 'vendite',
    'description' => 'Fatture di base, senza generazione PDF.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'invoices' => [
            'table' => 'invoices',
            'fields' => [
                'invoice_number' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Numero fattura',
                    'base' => true,
                    'help' => "Va tenuto unico e progressivo a mano - non c'e ancora una numerazione automatica.",
                ],
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'issue_date' => [
                    'sql' => 'DATE',
                    'label' => 'Data emissione',
                    'base' => true,
                    'format' => 'date',
                ],
                'due_date' => [
                    'sql' => 'DATE',
                    'label' => 'Data scadenza',
                    'defaultRequired' => true,
                    'format' => 'date',
                ],
                'stage' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Stato',
                    'base' => true,
                    'help' => "Vocabolario libero: valori tipici sono 'bozza', 'emessa', 'pagata', 'scaduta', 'annullata'.",
                ],
                'description' => [
                    'sql' => 'TEXT',
                    'label' => 'Descrizione',
                    'defaultRequired' => true,
                    'help' => "Cosa viene fatturato - qui in testo libero: non c'e ancora un elenco di righe con prodotti/servizi/quantita separati.",
                ],
                'subtotal' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Imponibile',
                    'base' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                ],
                'tax_amount' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'IVA',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                ],
                'total' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Totale',
                    'base' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                    'help' => 'Non calcolato automaticamente da imponibile+IVA - un valore a se, inserito da chi compila.',
                ],
                'payment_method' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Metodo di pagamento',
                    'defaultRequired' => true,
                    'help' => "Vocabolario libero: valori tipici sono 'bonifico', 'contanti', 'carta', 'RID/SDD'.",
                ],
            ],

            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            [
                                'title' => 'Fattura',
                                'area' => 'main',
                                'fields' => ['invoice_number', 'customer_id', 'description'],
                            ],
                            [
                                'title' => 'Date e stato',
                                'area' => 'sidebar',
                                'fields' => ['issue_date', 'due_date', 'stage'],
                            ],
                            [
                                'title' => 'Importi',
                                'area' => 'sidebar',
                                'fields' => ['subtotal', 'tax_amount', 'total', 'payment_method'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

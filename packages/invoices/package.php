<?php

declare(strict_types=1);

/**
 * Fatture, anche elettroniche.
 *
 * La fattura ha una riga sola (descrizione, imponibile, aliquota): basta
 * per canoni, rate e lavori a corpo, che sono le fatture di un'agenzia. Il
 * numero e' progressivo per anno (number_year + number_seq, il testo
 * "2026/12" in invoice_number), assegnato da App\Invoicing\InvoiceService
 * all'emissione.
 *
 * Fattura elettronica: App\Invoicing\FatturaPaXml genera l'XML FatturaPA
 * (sempre scaricabile); l'invio allo SdI passa da un fornitore collegato
 * (Fatture in Cloud o Aruba, vedi App\Invoicing\InvoiceProviderInterface).
 * sdi_status/sdi_reference/sent_at li scrive l'invio.
 *
 * company_profiles: i dati di chi emette le fatture (una riga), quelli che
 * finiscono nel CedentePrestatore dell'XML.
 *
 * Nessun PDF di cortesia generato qui (servirebbe una libreria): lo fanno
 * i fornitori collegati.
 */
return [
    'name' => 'invoices',
    'label' => 'Fatture',
    'category' => 'vendite',
    'description' => 'Fatture con numerazione automatica, fattura elettronica (XML FatturaPA) e invio allo SdI tramite Fatture in Cloud o Aruba.',
    'dependsOn' => ['customers'],
    'selectableDirectly' => true,
    'entities' => [
        'invoices' => [
            'table' => 'invoices',
            'fields' => [
                'document_type' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Documento',
                    'defaultRequired' => true,
                    'options' => ['fattura', 'pro forma'],
                    'help' => "Pro forma: richiesta di pagamento senza valore fiscale, numerata a parte (PF); a incasso avvenuto si trasforma in fattura.",
                ],
                'invoice_number' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Numero',
                    'base' => true,
                    'nullable' => true,
                    'help' => "Si assegna da solo all'emissione (es. 2026/12).",
                ],
                'number_year' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Anno numerazione',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'number_seq' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Progressivo',
                    'defaultRequired' => true,
                    'format' => 'integer',
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
                    'label' => 'Data',
                    'base' => true,
                    'format' => 'date',
                ],
                'due_date' => [
                    'sql' => 'DATE',
                    'label' => 'Scadenza',
                    'defaultRequired' => true,
                    'format' => 'date',
                ],
                'stage' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['bozza', 'emessa', 'pagata', 'annullata'],
                ],
                'description' => [
                    'sql' => 'TEXT',
                    'label' => 'Descrizione',
                    'defaultRequired' => true,
                ],
                'subtotal' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Imponibile',
                    'base' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                ],
                'tax_rate_percent' => [
                    'sql' => 'DECIMAL(5,2)',
                    'label' => 'Aliquota IVA (%)',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                ],
                'tax_amount' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'IVA',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                    'help' => "Vuota: si calcola dall'aliquota.",
                ],
                'total' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Totale',
                    'base' => true,
                    'nullable' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                    'help' => 'Vuoto: imponibile piu\' IVA.',
                ],
                'payment_method' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Metodo di pagamento',
                    'defaultRequired' => true,
                    'options' => ['bonifico', 'carta', 'addebito SEPA', 'PayPal', 'contanti'],
                ],
                'customer_service_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Servizio',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'installment_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Incasso',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'sdi_status' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Fattura elettronica',
                    'defaultRequired' => true,
                    'options' => ['da inviare', 'inviata', 'consegnata', 'scartata', 'non consegnata'],
                ],
                'sdi_provider' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Inviata con',
                    'defaultRequired' => true,
                ],
                'sdi_reference' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Riferimento invio',
                    'defaultRequired' => true,
                ],
                'sent_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Inviata il',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
            ],

            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Fattura', 'area' => 'main', 'fields' => ['document_type', 'customer_id', 'description', 'invoice_number']],
                            ['title' => 'Date e stato', 'area' => 'sidebar', 'fields' => ['issue_date', 'due_date', 'stage']],
                            ['title' => 'Importi', 'area' => 'sidebar', 'fields' => ['subtotal', 'tax_rate_percent', 'tax_amount', 'total', 'payment_method']],
                        ],
                    ],
                ],
            ],
        ],

        'company_profiles' => [
            'table' => 'company_profiles',
            'fields' => [
                'company_name' => ['sql' => 'VARCHAR(150)', 'label' => 'Ragione sociale', 'base' => true],
                'vat_number' => ['sql' => 'VARCHAR(20)', 'label' => 'Partita IVA', 'base' => true, 'format' => 'partita_iva'],
                'tax_code' => ['sql' => 'VARCHAR(20)', 'label' => 'Codice fiscale', 'defaultRequired' => true, 'help' => "Se diverso dalla partita IVA."],
                'tax_regime' => [
                    'sql' => 'VARCHAR(4)',
                    'label' => 'Regime fiscale',
                    'base' => true,
                    'options' => ['RF01', 'RF19', 'RF02', 'RF04', 'RF05', 'RF18'],
                    'help' => 'RF01 ordinario, RF19 forfettario.',
                ],
                'address' => ['sql' => 'VARCHAR(190)', 'label' => 'Indirizzo', 'base' => true],
                // I suggerimenti del CAP compilano comune e provincia.
                'postal_code' => ['sql' => 'VARCHAR(10)', 'label' => 'CAP', 'base' => true, 'autocomplete' => ['source' => '/geo/cap/cerca', 'freeText' => true], 'width' => 'quarter'],
                'city' => ['sql' => 'VARCHAR(100)', 'label' => 'Comune', 'base' => true],
                'province' => ['sql' => 'VARCHAR(2)', 'label' => 'Provincia (sigla)', 'base' => true],
                'country' => ['sql' => 'VARCHAR(2)', 'label' => 'Nazione', 'defaultRequired' => true, 'help' => 'IT'],
                'email' => ['sql' => 'VARCHAR(190)', 'label' => 'Email', 'defaultRequired' => true, 'format' => 'email'],
                'pec' => ['sql' => 'VARCHAR(190)', 'label' => 'PEC', 'defaultRequired' => true, 'format' => 'email'],
                'phone' => ['sql' => 'VARCHAR(30)', 'label' => 'Telefono', 'defaultRequired' => true],
                'iban' => ['sql' => 'VARCHAR(34)', 'label' => 'IBAN per i bonifici', 'defaultRequired' => true, 'format' => 'iban'],
                'default_tax_rate' => ['sql' => 'DECIMAL(5,2)', 'label' => 'Aliquota IVA abituale (%)', 'defaultRequired' => true, 'format' => 'decimal', 'formatOptions' => ['precision' => 2]],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Azienda', 'area' => 'main', 'fields' => ['company_name', 'vat_number', 'tax_code', 'tax_regime']],
                            ['title' => 'Sede', 'area' => 'main', 'fields' => ['address', 'postal_code', 'city', 'province', 'country']],
                            ['title' => 'Contatti e pagamento', 'area' => 'sidebar', 'fields' => ['email', 'pec', 'phone', 'iban', 'default_tax_rate']],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

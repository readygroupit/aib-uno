<?php

declare(strict_types=1);

/**
 * Scadenzario degli incassi: una riga per ogni pagamento atteso da un
 * cliente (acconto, saldo, rata, rinnovo, unica soluzione). Le righe
 * nascono dal piano di pagamento di un servizio del cliente
 * (App\Billing\PaymentPlanService) o a mano per un incasso a se'.
 *
 * "In ritardo" non e' uno stato salvato: e' una riga 'da incassare' con
 * la scadenza passata, calcolata quando si legge (vedi
 * InstallmentRepository), cosi' non serve un job che aggiorni gli stati.
 */
return [
    'name' => 'installments',
    'label' => 'Scadenzario incassi',
    'category' => 'vendite',
    'description' => 'Incassi attesi dai clienti: acconti, saldi, rate e rinnovi, con le scadenze.',
    'dependsOn' => ['customers', 'customer_services'],
    'selectableDirectly' => true,
    'entities' => [
        'installments' => [
            'table' => 'installments',
            'fields' => [
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'customer_service_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Servizio',
                    'base' => true,
                    'nullable' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customer_services', 'table' => 'customer_services', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customer_services/cerca'],
                ],
                'kind' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Tipo',
                    'base' => true,
                    'options' => ['unica soluzione', 'acconto', 'saldo', 'rata', 'rinnovo'],
                ],
                'description' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Descrizione',
                    'base' => true,
                ],
                'due_date' => [
                    'sql' => 'DATE',
                    'label' => 'Scadenza',
                    'base' => true,
                    'format' => 'date',
                ],
                'amount' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Importo',
                    'base' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                ],
                'stage' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['da incassare', 'incassato', 'annullato'],
                ],
                'paid_at' => [
                    'sql' => 'DATE',
                    'label' => 'Incassato il',
                    'defaultRequired' => true,
                    'format' => 'date',
                    'showIf' => ['field' => 'stage', 'in' => ['incassato']],
                ],
                'payment_method' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Metodo di pagamento',
                    'defaultRequired' => true,
                    'options' => ['bonifico', 'carta', 'addebito SEPA', 'PayPal', 'contanti'],
                ],
                'period_start' => [
                    'sql' => 'DATE',
                    'label' => 'Periodo dal',
                    'defaultRequired' => true,
                    'format' => 'date',
                    'help' => 'Per un rinnovo: il periodo che questo pagamento copre.',
                ],
                'period_end' => [
                    'sql' => 'DATE',
                    'label' => 'Periodo al',
                    'defaultRequired' => true,
                    'format' => 'date',
                ],
                'reminder_sent_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Ultimo sollecito',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'notes' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Note',
                    'defaultRequired' => true,
                ],
                // Pagamento online (Stripe, PayPal): il link mandato al cliente e
                // il riferimento dell'incasso. Li scrive App\Payments\PaymentService.
                'payment_provider' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Pagato con',
                    'defaultRequired' => true,
                ],
                'payment_reference' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Riferimento pagamento',
                    'defaultRequired' => true,
                ],
                'payment_link_url' => [
                    'sql' => 'VARCHAR(500)',
                    'label' => 'Link di pagamento',
                    'defaultRequired' => true,
                ],
                // La fattura emessa per questo incasso (pacchetto 'invoices', se
                // c'e'): niente FK, lo scadenzario funziona anche senza fatture.
                'invoice_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Fattura',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
            ],

            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            [
                                'title' => 'Incasso',
                                'area' => 'main',
                                'fields' => ['customer_id', 'customer_service_id', 'kind', 'description', 'notes'],
                            ],
                            [
                                'title' => 'Periodo',
                                'area' => 'main',
                                'fields' => ['period_start', 'period_end'],
                            ],
                            [
                                'title' => 'Importo e stato',
                                'area' => 'sidebar',
                                'fields' => ['amount', 'due_date', 'stage', 'paid_at', 'payment_method'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

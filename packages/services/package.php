<?php

declare(strict_types=1);

/**
 * Servizio (non un bene fisico) offerto al cliente - stesso principio di
 * 'catalog' ma senza scorte/peso/spedizione, con la durata al posto
 * della quantita in magazzino.
 */
return [
    'name' => 'services',
    'label' => 'Servizi',
    'category' => 'vendite',
    'description' => 'Servizi offerti, con prezzo e durata.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'services' => [
            'table' => 'services',
            'fields' => [
                'name' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Nome',
                    'base' => true,
                ],
                'category' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Categoria',
                    'defaultRequired' => true,
                ],
                'description' => [
                    'sql' => 'TEXT',
                    'label' => 'Descrizione',
                    'defaultRequired' => true,
                ],
                'stage' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['attivo', 'non disponibile'],
                ],
                'price' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Prezzo di listino',
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
                'duration_minutes' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Durata (minuti)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => "Tempo di lavoro tipico (es. uno shooting di una giornata = 480): servira' a pianificare le giornate.",
                ],
                'billing_type' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Come si paga',
                    'defaultRequired' => true,
                    'options' => ['una tantum', 'ricorrente'],
                    'help' => "Ricorrente: un canone che si rinnova (es. hosting del sito ogni anno, social ogni mese).",
                ],
                'recurrence' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Ogni quanto',
                    'defaultRequired' => true,
                    'options' => ['mensile', 'trimestrale', 'semestrale', 'annuale'],
                    'showIf' => ['field' => 'billing_type', 'in' => ['ricorrente']],
                ],
                'cost_price' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Costo per voi',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                    'help' => "Quanto costa a voi (fornitore, strumenti): serve a vedere il margine.",
                ],
                'supplier_customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Fornitore',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                    'help' => 'Se il servizio lo realizza un fornitore esterno.',
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
                                'fields' => ['name', 'category', 'description', 'stage'],
                            ],
                            [
                                'title' => 'Prezzo',
                                'area' => 'sidebar',
                                'fields' => ['billing_type', 'recurrence', 'price', 'cost_price', 'tax_rate_percent'],
                            ],
                            [
                                'title' => 'Chi lo fa e quanto dura',
                                'area' => 'main',
                                'fields' => ['supplier_customer_id', 'duration_minutes'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

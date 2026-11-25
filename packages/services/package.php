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
                    'width' => 'half',
                    'label' => 'Nome',
                    'base' => true,
                ],
                'category' => [
                    'sql' => 'VARCHAR(100)',
                    'width' => 'quarter',
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
                    'width' => 'quarter',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['attivo', 'non disponibile'],
                ],
                'price' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Prezzo indicativo',
                    'base' => true,
                    'nullable' => true,
                    'help' => 'Facoltativo: il prezzo vero si decide nel preventivo.',
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
                    'required' => true,
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Come si paga',
                    'defaultRequired' => true,
                    'options' => ['una tantum', 'ricorrente'],
                    'help' => "Ricorrente: un canone che si rinnova (es. hosting del sito ogni anno, social ogni mese).",
                ],
                'recurrence' => [
                    'required' => true,
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
                        'key' => 'servizio',
                        'label' => 'Il servizio',
                        'boxes' => [
                            [
                                'title' => 'Servizio',
                                'description' => 'Come si chiama, che tipo di servizio e\' e cosa comprende.',
                                'area' => 'main',
                                'fields' => ['name', 'category', 'stage', 'description'],
                                'guide' => "La descrizione la leggono i colleghi e l'assistente: scrivi cosa si fa, non solo il nome. \"Non disponibile\" lo toglie dalle proposte senza cancellarlo.",
                            ],
                            [
                                'title' => 'Chi lo fa e quanto dura',
                                'description' => 'Solo se lo fa qualcuno fuori dall\'agenzia.',
                                'area' => 'sidebar',
                                'fields' => ['supplier_customer_id', 'duration_minutes'],
                                'guide' => "Il fornitore serve solo se il servizio lo fa qualcuno fuori dall'agenzia. La durata e' indicativa: le date vere si fissano nel progetto.",
                            ],
                        ],
                    ],
                    [
                        'key' => 'vendita',
                        'label' => 'Vendita',
                        'boxes' => [
                            [
                                'title' => 'Prezzo e costi',
                                'description' => 'Il prezzo e\' indicativo: quello vero si decide nel preventivo.',
                                'area' => 'main',
                                'fields' => ['price', 'cost_price', 'tax_rate_percent'],
                                'guide' => [
                                    'text' => "Il prezzo vero si decide nel preventivo, cliente per cliente. Qui puoi mettere un prezzo indicativo: la riga del preventivo parte da li'.",
                                    'tip' => "Senza prezzo indicativo il preventivo chiede di scriverlo prima di inviarlo. Il costo per voi resta interno: il cliente non lo vede.",
                                ],
                            ],
                            [
                                'title' => 'Come si fattura',
                                'description' => 'Una volta o a canone.',
                                'area' => 'sidebar',
                                'fields' => ['billing_type', 'recurrence'],
                                'guide' => "Una tantum si paga una volta; ricorrente si ripete (es. ogni mese) finche' il cliente ha il servizio.",
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

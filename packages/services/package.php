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
                    'help' => "Vocabolario libero: valori tipici sono 'attivo', 'non disponibile'.",
                ],
                'price' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Prezzo',
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
                                'title' => 'Prezzo e durata',
                                'area' => 'sidebar',
                                'fields' => ['price', 'tax_rate_percent', 'duration_minutes'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

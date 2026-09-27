<?php

declare(strict_types=1);

/**
 * Prodotto di catalogo generico - spunto da readyecommerce2 (prezzi
 * netto/vendita, IVA, scorte) e da Istrumed (soglia sotto-scorta,
 * garanzia), ridotto al sottoinsieme che serve a un catalogo qualunque,
 * non a un intero e-commerce (niente varianti/attributi/kit: quello
 * resta la complessita' specifica di readyecommerce2, non generalizzata
 * qui - vedi il commento su 'immagine' sotto per lo stesso principio).
 *
 * 'stage', non 'status': 'status' e' riservato (colonna di sistema per
 * l'eliminazione logica di ogni tabella - vedi
 * App\Package\PackageInstaller::RESERVED_FIELD_NAMES).
 */
return [
    'name' => 'catalog',
    'label' => 'Catalogo',
    'category' => 'vendite',
    'description' => 'Catalogo prodotti con prezzi, scorte e categoria.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'products' => [
            'table' => 'products',
            'fields' => [
                'name' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Nome',
                    'base' => true,
                ],
                'sku' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Codice (SKU)',
                    'defaultRequired' => true,
                ],
                'category' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Categoria',
                    'defaultRequired' => true,
                    'help' => "Testo libero, non un elenco fisso - una vera gerarchia di categorie e' una complessita' non ancora costruita qui.",
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
                    'help' => "Vocabolario libero: valori tipici sono 'in vendita', 'non in vendita', 'esaurito'.",
                ],
                'cost_price' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => "Prezzo d'acquisto",
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                ],
                'selling_price' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Prezzo di vendita',
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
                'stock_quantity' => [
                    'sql' => 'INT',
                    'label' => 'Quantita in magazzino',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'low_stock_threshold' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Soglia sotto-scorta',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => 'Sotto questa quantita il prodotto va considerato da riordinare - solo un dato, nessun avviso automatico ancora costruito.',
                ],
                'weight_kg' => [
                    'sql' => 'DECIMAL(8,3)',
                    'label' => 'Peso (kg)',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 3],
                ],
                'image_url' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Immagine (URL)',
                    'defaultRequired' => true,
                    'help' => "Solo un indirizzo esterno, non un caricamento file - quella e' un'infrastruttura a se' (vedi App\\Model\\Attachment nel framework), non ancora collegata qui.",
                ],
            ],

            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            [
                                'title' => 'Prodotto',
                                'area' => 'main',
                                'fields' => ['name', 'sku', 'category', 'description', 'stage', 'image_url'],
                            ],
                            [
                                'title' => 'Prezzi',
                                'area' => 'sidebar',
                                'fields' => ['cost_price', 'selling_price', 'tax_rate_percent'],
                            ],
                            [
                                'title' => 'Magazzino',
                                'area' => 'sidebar',
                                'fields' => ['stock_quantity', 'low_stock_threshold', 'weight_kg'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

<?php

declare(strict_types=1);

/**
 * Intervento/visita tecnica presso un cliente - spunto diretto da
 * Istrumed (vedi 'intervention': stato della lavorazione separato dal
 * tipo di operazione, tecnico assegnato, costi separati per uscita e
 * manodopera). Qui semplificato: niente righe di dettaglio
 * prodotto/ricambio (InterventionRow in Istrumed), solo la testata.
 */
return [
    'name' => 'interventions',
    'label' => 'Interventi',
    'category' => 'operativita',
    'description' => 'Interventi tecnici presso i clienti, con costi e tecnico assegnato.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'interventions' => [
            'table' => 'interventions',
            'fields' => [
                'title' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Titolo',
                    'base' => true,
                ],
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                ],
                'scheduled_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Data programmata',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'stage' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Stato lavorazione',
                    'base' => true,
                    'help' => "Vocabolario libero: valori tipici sono 'da assegnare', 'assegnato', 'sospeso', 'completato', 'chiuso', 'annullato'.",
                ],
                'operation_type' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Tipo operazione',
                    'defaultRequired' => true,
                    'help' => "Vocabolario libero: valori tipici sono 'consegna', 'riparazione', 'entrambi'.",
                ],
                'assigned_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Tecnico assegnato',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                ],
                'call_out_fee' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Costo uscita',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                ],
                'labor_cost' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Costo manodopera',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                ],
                'total' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Totale da incassare',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                    'help' => 'Non calcolato automaticamente dalla somma dei costi - un totale a se, deciso da chi compila (es. per applicare uno sconto).',
                ],
                'payment_method' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Metodo di pagamento',
                    'defaultRequired' => true,
                    'help' => "Vocabolario libero: valori tipici sono 'contanti', 'bonifico', 'carta', 'assegno', 'gratuito'.",
                ],
                'notes' => [
                    'sql' => 'TEXT',
                    'label' => 'Note',
                    'defaultRequired' => true,
                ],
            ],

            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            [
                                'title' => 'Intervento',
                                'area' => 'main',
                                'fields' => ['title', 'customer_id', 'scheduled_at', 'stage', 'operation_type', 'notes'],
                            ],
                            [
                                'title' => 'Assegnazione',
                                'area' => 'sidebar',
                                'fields' => ['assigned_user_id'],
                            ],
                            [
                                'title' => 'Costi',
                                'area' => 'sidebar',
                                'fields' => ['call_out_fee', 'labor_cost', 'total', 'payment_method'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

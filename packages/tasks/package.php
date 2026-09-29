<?php

declare(strict_types=1);

/**
 * Attivita'/promemoria assegnato a un operatore, collegato in modo
 * polimorfico a qualunque entita' (entity_type + entity_id, stesso
 * pattern gia' usato dal framework per 'attachments' - vedi Fase 0).
 * Polimorfico = nessuna FOREIGN KEY possibile su entity_id, per
 * costruzione: puo' puntare a righe di tabelle diverse.
 *
 * entity_type/entity_id restano due campi di testo/numero semplici nel
 * form (non un selettore intelligente "scegli prima il tipo poi
 * l'elemento") - funzionale ma migliorabile, coerente con "meglio
 * onesto che finto": vedi 'help' sotto.
 */
return [
    'name' => 'tasks',
    'label' => 'Attivita',
    'category' => 'operativita',
    'description' => 'Attivita\' e promemoria assegnati a un operatore.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'tasks' => [
            'table' => 'tasks',
            'fields' => [
                'title' => [
                    'sql' => 'VARCHAR(200)',
                    'label' => 'Titolo',
                    'base' => true,
                ],
                'description' => [
                    'sql' => 'TEXT',
                    'label' => 'Descrizione',
                    'defaultRequired' => true,
                ],
                'due_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Scadenza',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'stage' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Stato attivita',
                    'base' => true,
                    'help' => "Vocabolario libero: valori tipici sono 'da fare', 'in corso', 'fatta', 'annullata'.",
                ],
                'assigned_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Operatore assegnato',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/users/cerca'],
                ],
                'completed_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Completata il',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'entity_type' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Tipo entita collegata',
                    'base' => true,
                    'nullable' => true,
                    'help' => "Es. 'leads', 'customers' - il nome del pacchetto a cui questa attivita' si riferisce. Facoltativo: un'attivita' puo' anche non essere collegata a nulla.",
                ],
                'entity_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Id entita collegata',
                    'base' => true,
                    'nullable' => true,
                    'format' => 'integer',
                    'help' => "L'id della riga specifica dentro 'Tipo entita collegata' (es. il contatto numero 12).",
                ],
            ],

            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            [
                                'title' => 'Attivita',
                                'area' => 'main',
                                'fields' => ['title', 'description', 'due_at', 'stage'],
                            ],
                            [
                                'title' => 'Assegnazione',
                                'area' => 'sidebar',
                                'fields' => ['assigned_user_id', 'completed_at'],
                            ],
                            [
                                'title' => 'Collegamento',
                                'area' => 'sidebar',
                                'fields' => ['entity_type', 'entity_id'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

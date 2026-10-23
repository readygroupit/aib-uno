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
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['da fare', 'in corso', 'fatta', 'annullata'],
                ],
                'assigned_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Chi la fa',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
                'planned_date' => [
                    'sql' => 'DATE',
                    'label' => 'Da fare il',
                    'defaultRequired' => true,
                    'format' => 'date',
                    'help' => "Il giorno in cui farla. La scadenza e' l'ultimo giorno utile.",
                ],
                'priority' => [
                    'sql' => 'VARCHAR(10)',
                    'label' => 'Priorita',
                    'defaultRequired' => true,
                    'options' => ['alta', 'normale', 'bassa'],
                ],
                'estimated_minutes' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Tempo previsto (minuti)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'actual_minutes' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Tempo impiegato (minuti)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'position' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Ordine',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => "Per le fasi di una commessa: la sequenza in cui si fanno.",
                ],
                'waits_for_client' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Aspetta il cliente',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                    'help' => "Es. testi, foto, un'approvazione: finche' non arriva, il lavoro e' fermo dal cliente.",
                ],
                'checklist' => [
                    'sql' => 'TEXT',
                    'label' => 'Checklist',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                    'help' => "Un punto per riga; '[x]' davanti a quelli fatti.",
                ],
                // La fase del modello da cui nasce (tipi di commessa): serve a
                // confrontare i tempi dello stesso lavoro tra commesse diverse.
                // Niente FK: 'tasks' non dipende dalle commesse.
                'source_step_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Fase del modello',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'recurrence' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Si ripete',
                    'defaultRequired' => true,
                    'options' => ['ogni giorno', 'ogni settimana', 'ogni mese'],
                    'help' => 'Fatta questa, si crea da sola la prossima.',
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
                                'fields' => ['title', 'description', 'checklist'],
                            ],
                            [
                                'title' => 'Quando',
                                'area' => 'main',
                                'fields' => ['planned_date', 'due_at', 'recurrence', 'position'],
                            ],
                            [
                                'title' => 'Chi e come',
                                'area' => 'sidebar',
                                'fields' => ['assigned_user_id', 'stage', 'priority', 'waits_for_client'],
                            ],
                            [
                                'title' => 'Tempo',
                                'area' => 'sidebar',
                                'fields' => ['estimated_minutes', 'actual_minutes', 'completed_at'],
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

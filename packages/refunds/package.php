<?php

declare(strict_types=1);

/**
 * Fase economica/amministrativa di una pratica (agente "Amministrazione e
 * rimborsi" del brief Assilevi, 6.8): importo richiesto/accettato, stato
 * del pagamento al cliente, riferimento della fattura. Non muove soldi
 * per davvero e non integra un sistema di fatturazione esterno (il brief
 * cita "fatturazione Brains" senza specificarla): invoice_reference resta
 * un campo libero, un rimando testuale a dove sta la fattura vera, non
 * un collegamento tecnico.
 *
 * Una pratica -> zero o una riga qui (non una lista): il rimborso e' UN
 * esito economico per pratica, non uno storico di piu' movimenti - se in
 * futuro servisse tracciare piu' pagamenti parziali nel tempo si
 * aggiungerebbe una entity separata (stesso pattern di communications/
 * communication_contents), non si forza qui.
 */
return [
    'name' => 'refunds',
    'label' => 'Rimborsi',
    'category' => 'operativita',
    'description' => 'Importi, stato pagamento e fattura del rimborso per ogni pratica.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'refunds' => [
            'table' => 'refunds',
            'fields' => [
                'case_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Pratica',
                    'base' => true,
                    'references' => ['package' => 'cases', 'table' => 'cases', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/cases/cerca'],
                ],
                'amount_claimed' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Importo richiesto',
                    'defaultRequired' => false,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                ],
                'amount_accepted' => [
                    'sql' => 'DECIMAL(10,2)',
                    'label' => 'Importo accettato',
                    'defaultRequired' => false,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                ],
                'payment_status' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Stato pagamento',
                    'base' => true,
                ],
                'paid_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Pagato il',
                    'defaultRequired' => false,
                ],
                'invoice_reference' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Riferimento fattura',
                    'defaultRequired' => false,
                    'help' => "Numero o riferimento della fattura nel sistema di fatturazione usato - solo testo libero, nessun collegamento tecnico.",
                ],
                'notes' => [
                    'sql' => 'TEXT',
                    'label' => 'Note',
                    'defaultRequired' => false,
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            [
                                'title' => 'Importi',
                                'area' => 'main',
                                'fields' => ['amount_claimed', 'amount_accepted', 'notes'],
                            ],
                            [
                                'title' => 'Pagamento',
                                'area' => 'sidebar',
                                'fields' => ['payment_status', 'paid_at', 'invoice_reference'],
                            ],
                            [
                                'title' => 'Collegamento',
                                'area' => 'sidebar',
                                'fields' => ['case_id'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

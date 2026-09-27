<?php

declare(strict_types=1);

/**
 * Campagna marketing (email) + coda di invio. Due entita' nello stesso
 * pacchetto: 'campaigns' (la campagna, con oggetto/testo/stato) e
 * 'campaign_emails' (una riga per ogni destinatario da spedire, con
 * stato proprio - in_coda/inviata/fallita). La campagna non genera
 * automaticamente le righe della coda (nessun motore di segmentazione
 * costruito qui): 'target_segment' resta una descrizione testuale di chi
 * si vuole raggiungere, la coda va popolata a parte. Nessun invio vero
 * implementato - vedi App\Service\MailService gia' nel framework per
 * dove si aggancerebbe un domani, e App\Job\JobRunner/JobHandlerRegistry
 * per come si schedulerebbe l'elaborazione della coda.
 */
return [
    'name' => 'campaigns',
    'label' => 'Campagne marketing',
    'category' => 'crm',
    'description' => 'Campagne email marketing e coda di invio.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'campaigns' => [
            'table' => 'campaigns',
            'fields' => [
                'name' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Nome campagna',
                    'base' => true,
                ],
                'subject' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Oggetto email',
                    'defaultRequired' => true,
                ],
                'body' => [
                    'sql' => 'TEXT',
                    'label' => 'Testo email',
                    'defaultRequired' => true,
                ],
                'stage' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Stato',
                    'base' => true,
                    'help' => "Vocabolario libero: valori tipici sono 'bozza', 'programmata', 'in corso', 'completata', 'annullata'.",
                ],
                'scheduled_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Programmata per',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'target_segment' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Destinatari',
                    'defaultRequired' => true,
                    'help' => "Descrizione testuale di chi si vuole raggiungere (es. 'tutti i lead in stato nuovo') - non esiste ancora un motore di segmentazione che lo traduca in un elenco vero.",
                ],
                'sent_count' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Email inviate',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => "Contatore informativo, non aggiornato automaticamente finche' non esiste un invio vero.",
                ],
            ],

            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            [
                                'title' => 'Campagna',
                                'area' => 'main',
                                'fields' => ['name', 'subject', 'body'],
                            ],
                            [
                                'title' => 'Programmazione',
                                'area' => 'sidebar',
                                'fields' => ['stage', 'scheduled_at', 'target_segment', 'sent_count'],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'campaign_emails' => [
            'table' => 'campaign_emails',
            'fields' => [
                'campaign_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Campagna',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'campaigns', 'column' => 'id'],
                ],
                'recipient_email' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Destinatario',
                    'base' => true,
                    'format' => 'email',
                ],
                'lead_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Contatto collegato',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'nullable' => true,
                    'references' => ['package' => 'leads', 'table' => 'leads', 'column' => 'id'],
                ],
                'stage' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Stato invio',
                    'base' => true,
                    'help' => "Vocabolario libero: valori tipici sono 'in coda', 'inviata', 'fallita'.",
                ],
                'sent_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Inviata il',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                    'nullable' => true,
                ],
                'error_message' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Errore',
                    'defaultRequired' => true,
                    'nullable' => true,
                ],
            ],
        ],
    ],
];

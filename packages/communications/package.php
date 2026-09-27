<?php

declare(strict_types=1);

/**
 * Storico comunicazioni (email/WhatsApp/telefono...), collegato in modo
 * polimorfico a qualunque entita' (stesso pattern di 'tasks').
 *
 * Due entity, non una: 'communications' (metadati - canale, direzione,
 * stato, date) e 'communication_contents' (oggetto+corpo del messaggio).
 * Questa e' l'unica ragione legittima per dividere in piu' tabelle
 * discussa con l'utente: non opzionalita' di campo (quello resta su
 * un'unica tabella, vedi 'customers'/'leads'), ma pattern di accesso -
 * una lista di comunicazioni si interroga in continuazione (badge,
 * conteggi, timeline) senza mai leggere il corpo del messaggio, che
 * pesa e serve solo quando si apre una comunicazione specifica.
 * 'communication_contents' e' dichiarata dopo perche' referenzia
 * 'communications' (stesso pacchetto, ordine di creazione rilevante).
 */
return [
    'name' => 'communications',
    'label' => 'Comunicazioni',
    'category' => 'crm',
    'description' => 'Storico delle comunicazioni (email, telefono, ecc.) con ogni entita\'.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'communications' => [
            'table' => 'communications',
            'fields' => [
                'entity_type' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Tipo entita collegata',
                    'base' => true,
                    'help' => "Es. 'cases', 'leads', 'customers' - il nome del pacchetto a cui questa comunicazione si riferisce.",
                ],
                'entity_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Id entita collegata',
                    'base' => true,
                    'format' => 'integer',
                    'help' => "L'id della riga specifica dentro 'Tipo entita collegata' (es. la pratica numero 12).",
                ],
                'channel' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Canale',
                    'base' => true,
                ],
                'direction' => [
                    'sql' => 'VARCHAR(10)',
                    'label' => 'Direzione (in/out)',
                    'base' => true,
                ],
                'delivery_status' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Stato invio',
                    'base' => true,
                ],
                'template_code' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Modello utilizzato',
                    'defaultRequired' => false,
                ],
                'sent_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Inviata il',
                    'defaultRequired' => false,
                ],
                'read_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Letta il',
                    'defaultRequired' => false,
                ],
            ],
        ],
        'communication_contents' => [
            'table' => 'communication_contents',
            'fields' => [
                'communication_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Comunicazione',
                    'base' => true,
                    'references' => ['table' => 'communications', 'column' => 'id'],
                ],
                'subject' => [
                    'sql' => 'VARCHAR(200)',
                    'label' => 'Oggetto',
                    'defaultRequired' => false,
                ],
                'body' => [
                    'sql' => 'TEXT',
                    'label' => 'Corpo del messaggio',
                    'base' => true,
                ],
            ],
        ],
    ],
];

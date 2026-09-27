<?php

declare(strict_types=1);

/**
 * Traccia una singola esecuzione/proposta di un agente AI, collegata in
 * modo polimorfico a qualunque entita' (entity_type + entity_id, stesso
 * pattern di 'tasks'/'communications'). Disaccoppiato da 'scheduler' di
 * proposito: un agente puo' partire da un job schedulato (job_type =
 * 'ai_agent_task' nel payload) ma anche da un evento immediato (es. un
 * nuovo lead appena arrivato) - non tutte le esecuzioni AI sono
 * programmate nel tempo.
 *
 * requires_approval + approval_status realizzano direttamente il
 * requisito "supervisione umana" del documento Assilevi: le decisioni
 * delicate restano sempre sotto controllo dell'operatore, e ogni
 * proposta resta un record consultabile (audit log "agente, data,
 * pratica, dati consultati, azione proposta o effettuata ed eventuale
 * approvazione"). Non c'e' ancora nessun motore che genera o esegue le
 * proposte - questo pacchetto e' solo il dato, non il comportamento (il
 * "livello agenti" resta fuori scope, vedi le note di sessione).
 */
return [
    'name' => 'ai_agent_tasks',
    'label' => 'Attivita agenti AI',
    'category' => 'sistema',
    'description' => 'Traccia le esecuzioni e proposte di agenti AI, con stato di approvazione.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'ai_agent_tasks' => [
            'table' => 'ai_agent_tasks',
            'fields' => [
                'agent_code' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Agente',
                    'base' => true,
                ],
                'entity_type' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Tipo entita collegata',
                    'base' => true,
                ],
                'entity_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Id entita collegata',
                    'base' => true,
                ],
                'input_context' => [
                    'sql' => 'TEXT',
                    'label' => 'Dati consultati (JSON)',
                    'defaultRequired' => false,
                ],
                'suggested_action' => [
                    'sql' => 'TEXT',
                    'label' => 'Azione proposta (JSON)',
                    'defaultRequired' => false,
                ],
                'requires_approval' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Richiede approvazione umana',
                    'base' => true,
                ],
                'approval_status' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Stato approvazione',
                    'base' => true,
                ],
                'approved_by_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Approvato da',
                    'defaultRequired' => false,
                    'references' => ['table' => 'users', 'column' => 'id'],
                ],
                'approved_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Approvato il',
                    'defaultRequired' => false,
                ],
                'executed_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Eseguito il',
                    'defaultRequired' => false,
                ],
            ],
        ],
    ],
];

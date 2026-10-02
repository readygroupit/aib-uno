<?php

declare(strict_types=1);

/**
 * Agenti "colleghi digitali" e i messaggi che mandano all'operatore (vedi
 * App\Service\AgentService). Due entity: 'agents' (chi e', cosa fa, come e'
 * programmato) e 'agent_messages' (cio' che dice - il flusso in home).
 *
 * Ambiente dimostrativo: nessun agente chiama davvero Claude, i messaggi
 * sono costruiti da dati reali (contatti fermi, documenti mancanti...) con
 * testi preparati. 'autonomy' e 'is_enabled' pero' governano davvero il
 * comportamento; 'schedule' e 'rule_text' sono solo salvati.
 */
return [
    'name' => 'agents',
    'label' => 'Agenti',
    'category' => 'sistema',
    'description' => 'Agenti AI programmabili e messaggi che mandano all\'operatore.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'agents' => [
            'table' => 'agents',
            'fields' => [
                'code' => ['sql' => 'VARCHAR(40)', 'label' => 'Codice', 'base' => true],
                'name' => ['sql' => 'VARCHAR(80)', 'label' => 'Nome', 'base' => true],
                'role' => ['sql' => 'VARCHAR(120)', 'label' => 'Ruolo', 'base' => true],
                'bio' => ['sql' => 'TEXT', 'label' => 'Cosa fa', 'base' => true],
                'is_enabled' => ['sql' => 'TINYINT UNSIGNED', 'label' => 'Attivo', 'base' => true, 'format' => 'integer'],
                'schedule' => ['sql' => 'VARCHAR(30)', 'label' => 'Quando agisce', 'base' => true],
                'autonomy' => ['sql' => 'VARCHAR(20)', 'label' => 'Autonomia', 'base' => true, 'help' => "suggest, approval o auto."],
                'rule_text' => ['sql' => 'TEXT', 'label' => 'Regola', 'base' => true, 'nullable' => true],
                'last_run_at' => ['sql' => 'DATETIME', 'label' => 'Ultima esecuzione', 'base' => true, 'nullable' => true],
            ],
        ],
        'agent_messages' => [
            'table' => 'agent_messages',
            'fields' => [
                'agent_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Agente',
                    'base' => true,
                    'references' => ['package' => 'agents', 'table' => 'agents', 'column' => 'id'],
                ],
                'dedupe_key' => ['sql' => 'VARCHAR(80)', 'label' => 'Chiave anti-duplicato', 'base' => true],
                'body' => ['sql' => 'TEXT', 'label' => 'Messaggio', 'base' => true],
                'done_body' => ['sql' => 'TEXT', 'label' => 'Messaggio dopo l\'azione', 'base' => true, 'nullable' => true],
                'detail' => ['sql' => 'TEXT', 'label' => 'Bozza o dettaglio', 'base' => true, 'nullable' => true],
                'action_type' => ['sql' => 'VARCHAR(30)', 'label' => 'Azione', 'base' => true, 'nullable' => true],
                'action_label' => ['sql' => 'VARCHAR(60)', 'label' => 'Testo del pulsante', 'base' => true, 'nullable' => true],
                'link_href' => ['sql' => 'VARCHAR(190)', 'label' => 'Link', 'base' => true, 'nullable' => true],
                'entity_type' => ['sql' => 'VARCHAR(30)', 'label' => 'Tipo entita', 'base' => true, 'nullable' => true],
                'entity_id' => ['sql' => 'INT UNSIGNED', 'label' => 'Id entita', 'base' => true, 'nullable' => true, 'format' => 'integer'],
                'state' => ['sql' => 'VARCHAR(20)', 'label' => 'Stato', 'base' => true, 'help' => 'pending, done o dismissed.'],
                'result_text' => ['sql' => 'TEXT', 'label' => 'Esito', 'base' => true, 'nullable' => true],
            ],
        ],
    ],
];

<?php

declare(strict_types=1);

/**
 * Deposito credenziali per i connettori verso sistemi esterni (Jotform,
 * Google Drive, Stripe, ecc.) - il pattern generico stabilisce due stili
 * di autenticazione ('api_key' e 'oauth'), non un connettore specifico:
 * ogni servizio reale va implementato a parte (vedi App\Connector\
 * AbstractApiKeyConnector / AbstractOAuthConnector), questo pacchetto
 * mette solo la tabella su cui appoggiarsi, comune a entrambi gli stili
 * invece di una tabella diversa per ogni connettore.
 *
 * Una riga per connettore (connector_code): 'api_key'/'access_token'/
 * 'refresh_token'/'token_expires_at' coprono entrambi gli stili nella
 * stessa tabella - un connettore a chiave API usa solo api_key, uno
 * OAuth usa access_token/refresh_token/token_expires_at, i campi
 * dell'altro stile restano NULL. Niente vincolo di unicita' su
 * connector_code a livello di schema (il generatore di package non
 * supporta ancora UNIQUE KEY oltre alle foreign key) - la ricerca "la
 * credenziale per questo connettore" resta una query nel Repository, non
 * un vincolo del database.
 */
return [
    'name' => 'connectors',
    'label' => 'Connettori esterni',
    'category' => 'sistema',
    'description' => 'Credenziali per i collegamenti verso sistemi esterni.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'connector_credentials' => [
            'table' => 'connector_credentials',
            'fields' => [
                'connector_code' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Connettore',
                    'base' => true,
                ],
                'auth_type' => [
                    'sql' => "ENUM('api_key','oauth')",
                    'label' => 'Tipo autenticazione',
                    'base' => true,
                ],
                'api_key' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Chiave API',
                    'base' => true,
                    'nullable' => true,
                ],
                'access_token' => [
                    'sql' => 'TEXT',
                    'label' => 'Access token',
                    'base' => true,
                    'nullable' => true,
                ],
                'refresh_token' => [
                    'sql' => 'TEXT',
                    'label' => 'Refresh token',
                    'base' => true,
                    'nullable' => true,
                ],
                'token_expires_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Scadenza token',
                    'base' => true,
                    'nullable' => true,
                ],
                'extra_config' => [
                    'sql' => 'TEXT',
                    'label' => 'Configurazione aggiuntiva (JSON)',
                    'base' => true,
                    'nullable' => true,
                ],
            ],
        ],
        // Pagine Facebook e profili Instagram trovati con il collegamento a
        // Meta (MetaConnector), con il cliente a cui appartengono.
        'social_accounts' => [
            'table' => 'social_accounts',
            'fields' => [
                'network' => ['sql' => 'VARCHAR(20)', 'label' => 'Social', 'base' => true],
                'external_id' => ['sql' => 'VARCHAR(50)', 'label' => 'ID sul social', 'base' => true],
                'name' => ['sql' => 'VARCHAR(150)', 'label' => 'Nome', 'base' => true],
                'username' => ['sql' => 'VARCHAR(100)', 'label' => 'Nome utente', 'base' => true, 'nullable' => true],
                'page_id' => ['sql' => 'VARCHAR(50)', 'label' => 'Pagina Facebook', 'base' => true, 'nullable' => true],
                'access_token' => ['sql' => 'TEXT', 'label' => 'Token della pagina', 'base' => true, 'nullable' => true],
                'customer_id' => ['sql' => 'INT UNSIGNED', 'label' => 'Cliente', 'base' => true, 'nullable' => true],
            ],
        ],
        // Modalita' prova (TestModeService): cosa sarebbe successo davvero.
        'simulated_actions' => [
            'table' => 'simulated_actions',
            'fields' => [
                'effect' => ['sql' => 'VARCHAR(30)', 'label' => 'Cosa', 'base' => true],
                'summary' => ['sql' => 'VARCHAR(255)', 'label' => 'Riepilogo', 'base' => true],
                'detail' => ['sql' => 'TEXT', 'label' => 'Dettaglio', 'base' => true, 'nullable' => true],
                'recipient' => ['sql' => 'VARCHAR(190)', 'label' => 'Destinatario', 'base' => true, 'nullable' => true],
                'entity_type' => ['sql' => 'VARCHAR(50)', 'label' => 'Tipo', 'base' => true, 'nullable' => true],
                'entity_id' => ['sql' => 'INT UNSIGNED', 'label' => 'Elemento', 'base' => true, 'nullable' => true],
            ],
        ],
    ],
];

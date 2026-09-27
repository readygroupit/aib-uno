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
    ],
];

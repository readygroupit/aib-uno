<?php

declare(strict_types=1);

namespace App\Connector;

use App\Core\Container;
use App\Repository\ConnectorCredentialRepository;

/**
 * Base per connettori con autenticazione a chiave API singola (Jotform,
 * Stripe, e la maggior parte dei servizi con una sola chiave da
 * incollare) - niente scadenza, niente rinnovo, solo una chiave letta da
 * connector_credentials.api_key. Per i servizi a consenso (Google Drive e
 * simili, dove l'utente autorizza esplicitamente e si ottiene un token
 * rinnovabile) vedi AbstractOAuthConnector invece.
 */
abstract class AbstractApiKeyConnector implements ConnectorInterface
{
    protected readonly ConnectorCredentialRepository $credentials;

    public function __construct(Container $container)
    {
        $this->credentials = $container->get(ConnectorCredentialRepository::class);
    }

    protected function apiKey(): ?string
    {
        return $this->credentials->findByConnectorCode($this->code())?->apiKey;
    }
}

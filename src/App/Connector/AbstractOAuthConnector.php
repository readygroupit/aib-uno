<?php

declare(strict_types=1);

namespace App\Connector;

use App\Core\Container;
use App\Model\ConnectorCredential;
use App\Repository\ConnectorCredentialRepository;

/**
 * Base per connettori a consenso (OAuth) - Google Drive e simili, dove
 * l'operatore autorizza esplicitamente l'accesso e si ottengono un
 * access_token (di breve durata) e un refresh_token (per rinnovarlo senza
 * richiedere di nuovo il consenso). Il COME rinnovare (endpoint, corpo
 * della richiesta, parsing della risposta) e' specifico di ogni servizio
 * - va implementato dal connettore concreto in refreshAccessToken(),
 * qui c'e' solo la logica comune "il token e' scaduto? rinnovalo prima
 * di usarlo".
 */
abstract class AbstractOAuthConnector implements ConnectorInterface
{
    protected readonly ConnectorCredentialRepository $credentials;

    public function __construct(Container $container)
    {
        $this->credentials = $container->get(ConnectorCredentialRepository::class);
    }

    protected function accessToken(): ?string
    {
        $credential = $this->credentials->findByConnectorCode($this->code());
        if ($credential === null || $credential->accessToken === null) {
            return null;
        }

        $expired = $credential->tokenExpiresAt !== null
            && strtotime($credential->tokenExpiresAt) < time();

        return $expired ? $this->refreshAccessToken($credential) : $credential->accessToken;
    }

    /**
     * Deve chiamare l'endpoint di refresh del servizio, salvare il nuovo
     * access_token (e la nuova scadenza) su connector_credentials tramite
     * $this->credentials, e restituire il nuovo token pronto all'uso.
     */
    abstract protected function refreshAccessToken(ConnectorCredential $credential): ?string;
}

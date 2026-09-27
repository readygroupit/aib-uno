<?php

declare(strict_types=1);

namespace App\Connector;

/**
 * Un connettore verso un sistema esterno (Jotform, Google Drive, Stripe,
 * ecc.) - stesso principio di registrazione esplicita di PromptToolRegistry/
 * JobHandlerRegistry (vedi ConnectorRegistry), non auto-discovery. Questa
 * interfaccia e' lo strato comune minimo; l'autenticazione vera e propria
 * (dove prendere la chiave/il token, come rinnovarlo) vive nelle due
 * classi base AbstractApiKeyConnector/AbstractOAuthConnector, non qui -
 * un connettore concreto estende una di quelle due, non implementa
 * questa interfaccia direttamente.
 */
interface ConnectorInterface
{
    /** Identificativo stabile, stesso valore usato in connector_credentials.connector_code. */
    public function code(): string;

    public function label(): string;

    /**
     * Vero se le credenziali salvate ora sembrano valide e sufficienti
     * per usare il connettore - una chiamata leggera al servizio esterno
     * quando possibile, o almeno un controllo di presenza/coerenza dei
     * dati salvati.
     */
    public function test(): bool;
}

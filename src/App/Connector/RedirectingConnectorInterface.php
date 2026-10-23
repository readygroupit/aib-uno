<?php

declare(strict_types=1);

namespace App\Connector;

/**
 * Connettore che, dopo connect(), deve mandare il browser altrove (es. la
 * pagina di autorizzazione di Google): SetupController passa l'indirizzo a
 * setup.js, che ci va.
 */
interface RedirectingConnectorInterface
{
    public function redirectUrl(): ?string;
}

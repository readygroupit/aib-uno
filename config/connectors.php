<?php

declare(strict_types=1);

use App\Connector\ConnectorRegistry;

/**
 * Registra qui i connettori verso sistemi esterni (Jotform, Google Drive,
 * Stripe, ecc.), stesso principio di prompt-tools.php: un
 * $registry->register(NomeConnettore::class) per ognuno. Nessuno ancora
 * registrato - il pattern (ConnectorInterface, AbstractApiKeyConnector,
 * AbstractOAuthConnector, la tabella connector_credentials del pacchetto
 * 'connectors') e' pronto, i connettori concreti verso i servizi veri
 * vanno scritti quando servono davvero (ognuno ha una sua API, non ha
 * senso indovinarne la forma in anticipo).
 */
return static function (ConnectorRegistry $registry): void {
};

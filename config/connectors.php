<?php

declare(strict_types=1);

use App\Connector\ConnectorRegistry;
use App\Connector\GoogleSheetsConnector;
use App\Connector\JotformConnector;
use App\Connector\WhatsAppConnector;

/**
 * Registra qui i connettori verso sistemi esterni, stesso principio di
 * prompt-tools.php: un $registry->register(NomeConnettore::class) per
 * ognuno. Ogni connettore concreto ha la sua API: si scrive quando serve
 * davvero, non in anticipo.
 */
return static function (ConnectorRegistry $registry): void {
    $registry->register(JotformConnector::class);
    $registry->register(GoogleSheetsConnector::class);
    $registry->register(WhatsAppConnector::class);
};

<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Testata di una fattura: numero, stato, SdI, importi, cosa manca per
 * l'XML e le azioni (emetti, invia, scarica XML, pagata). Dati da
 * App\Invoicing\InvoiceOverviewService::detail().
 */
final class InvoiceSummaryComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'invoice-summary', 'url' => $config['url'] ?? null] + ($config['invoice'] ?? []);
    }
}

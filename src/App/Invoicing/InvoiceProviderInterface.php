<?php

declare(strict_types=1);

namespace App\Invoicing;

use App\Model\Invoice;

/**
 * Un fornitore per mandare la fattura elettronica allo SdI (Fatture in
 * Cloud, Aruba...). Riceve la fattura gia' emessa e il suo XML FatturaPA.
 */
interface InvoiceProviderInterface
{
    public function code(): string;

    public function label(): string;

    public function isConnected(): bool;

    /**
     * @param array $customer dati del cliente come per FatturaPaXml::build()
     * @return array{reference:string, status:string} status: 'inviata' o 'consegnata'
     */
    public function sendInvoice(Invoice $invoice, string $xml, string $fileName, array $customer): array;
}

<?php

declare(strict_types=1);

namespace App\Invoicing;

use App\Model\CompanyProfile;
use App\Model\Invoice;

/**
 * XML FatturaPA (versione FPR12, fattura tra privati) di una fattura a una
 * riga. E' il file che va allo SdI: lo si scarica sempre, e i fornitori
 * collegati (Aruba) lo inviano cosi' com'e'.
 *
 * Copre i casi di un'agenzia: regime ordinario (IVA) o forfettario
 * (aliquota 0, natura N2.2), cliente italiano con partita IVA o codice
 * fiscale, destinatario con codice SDI o PEC, pagamento con bonifico,
 * carta, SEPA o PayPal. Note di credito, ritenute, bollo, clienti esteri:
 * non ancora.
 */
final class FatturaPaXml
{
    private const NS = 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2';
    private const PAYMENT_CODES = ['bonifico' => 'MP05', 'carta' => 'MP08', 'addebito SEPA' => 'MP19', 'PayPal' => 'MP08', 'contanti' => 'MP01'];

    /**
     * @param array{name:string, vat:?string, taxCode:?string, address:?string, postalCode:?string, city:?string, province:?string, sdiCode:?string, pec:?string} $customer
     * @return array{xml:string, fileName:string}
     */
    public static function build(Invoice $invoice, CompanyProfile $company, array $customer): array
    {
        $problems = self::problems($invoice, $company, $customer);
        if ($problems !== []) {
            throw new InvoicingException('Per la fattura elettronica manca: ' . implode(', ', $problems) . '.');
        }

        $companyVat = self::digits((string) $company->vatNumber);
        $progressive = str_pad(base_convert((string) $invoice->id, 10, 36), 5, '0', STR_PAD_LEFT);
        $rate = (float) ($invoice->taxRatePercent ?? 0);
        $subtotal = (float) $invoice->subtotal;
        $tax = (float) ($invoice->taxAmount ?? round($subtotal * $rate / 100, 2));
        $total = (float) ($invoice->total ?? $subtotal + $tax);
        $sdiCode = strtoupper(trim((string) ($customer['sdiCode'] ?? '')));
        $usePec = ($sdiCode === '' || $sdiCode === '0000000') && !empty($customer['pec']);

        $x = new \XMLWriter();
        $x->openMemory();
        $x->setIndent(true);
        $x->startDocument('1.0', 'UTF-8');
        $x->startElementNs('p', 'FatturaElettronica', self::NS);
        $x->writeAttribute('versione', 'FPR12');
        $x->writeAttributeNs('xmlns', 'ds', null, 'http://www.w3.org/2000/09/xmldsig#');
        $x->writeAttributeNs('xmlns', 'xsi', null, 'http://www.w3.org/2001/XMLSchema-instance');

        $x->startElement('FatturaElettronicaHeader');
        $x->startElement('DatiTrasmissione');
        $x->startElement('IdTrasmittente');
        self::el($x, 'IdPaese', 'IT');
        self::el($x, 'IdCodice', $companyVat);
        $x->endElement();
        self::el($x, 'ProgressivoInvio', $progressive);
        self::el($x, 'FormatoTrasmissione', 'FPR12');
        self::el($x, 'CodiceDestinatario', $sdiCode !== '' ? $sdiCode : '0000000');
        if ($usePec) {
            self::el($x, 'PECDestinatario', (string) $customer['pec']);
        }
        $x->endElement();

        $x->startElement('CedentePrestatore');
        $x->startElement('DatiAnagrafici');
        $x->startElement('IdFiscaleIVA');
        self::el($x, 'IdPaese', 'IT');
        self::el($x, 'IdCodice', $companyVat);
        $x->endElement();
        if ($company->taxCode) {
            self::el($x, 'CodiceFiscale', strtoupper(trim($company->taxCode)));
        }
        $x->startElement('Anagrafica');
        self::el($x, 'Denominazione', mb_substr((string) $company->companyName, 0, 80));
        $x->endElement();
        self::el($x, 'RegimeFiscale', (string) ($company->taxRegime ?: 'RF01'));
        $x->endElement();
        self::address($x, (string) $company->address, (string) $company->postalCode, (string) $company->city, (string) $company->province, (string) ($company->country ?: 'IT'));
        $x->endElement();

        $x->startElement('CessionarioCommittente');
        $x->startElement('DatiAnagrafici');
        if (!empty($customer['vat'])) {
            $x->startElement('IdFiscaleIVA');
            self::el($x, 'IdPaese', 'IT');
            self::el($x, 'IdCodice', self::digits((string) $customer['vat']));
            $x->endElement();
        }
        if (!empty($customer['taxCode'])) {
            self::el($x, 'CodiceFiscale', strtoupper(trim((string) $customer['taxCode'])));
        }
        $x->startElement('Anagrafica');
        self::el($x, 'Denominazione', mb_substr((string) $customer['name'], 0, 80));
        $x->endElement();
        $x->endElement();
        self::address($x, (string) $customer['address'], (string) $customer['postalCode'], (string) $customer['city'], (string) $customer['province'], 'IT');
        $x->endElement();
        $x->endElement();

        $x->startElement('FatturaElettronicaBody');
        $x->startElement('DatiGenerali');
        $x->startElement('DatiGeneraliDocumento');
        self::el($x, 'TipoDocumento', 'TD01');
        self::el($x, 'Divisa', 'EUR');
        self::el($x, 'Data', (string) $invoice->issueDate);
        self::el($x, 'Numero', (string) $invoice->invoiceNumber);
        self::el($x, 'ImportoTotaleDocumento', self::money($total));
        if ($invoice->description) {
            self::el($x, 'Causale', mb_substr((string) $invoice->description, 0, 200));
        }
        $x->endElement();
        $x->endElement();

        $x->startElement('DatiBeniServizi');
        $x->startElement('DettaglioLinee');
        self::el($x, 'NumeroLinea', '1');
        self::el($x, 'Descrizione', mb_substr((string) ($invoice->description ?: 'Servizi'), 0, 1000));
        self::el($x, 'Quantita', '1.00');
        self::el($x, 'PrezzoUnitario', self::money($subtotal));
        self::el($x, 'PrezzoTotale', self::money($subtotal));
        self::el($x, 'AliquotaIVA', self::money($rate));
        if ($rate == 0.0) {
            self::el($x, 'Natura', 'N2.2');
        }
        $x->endElement();
        $x->startElement('DatiRiepilogo');
        self::el($x, 'AliquotaIVA', self::money($rate));
        if ($rate == 0.0) {
            self::el($x, 'Natura', 'N2.2');
        }
        self::el($x, 'ImponibileImporto', self::money($subtotal));
        self::el($x, 'Imposta', self::money($tax));
        if ($rate > 0) {
            self::el($x, 'EsigibilitaIVA', 'I');
        } else {
            self::el($x, 'RiferimentoNormativo', 'Operazione in franchigia da IVA, art. 1 c. 54-89 L. 190/2014');
        }
        $x->endElement();
        $x->endElement();

        $x->startElement('DatiPagamento');
        self::el($x, 'CondizioniPagamento', 'TP02');
        $x->startElement('DettaglioPagamento');
        self::el($x, 'ModalitaPagamento', self::PAYMENT_CODES[$invoice->paymentMethod] ?? 'MP05');
        if ($invoice->dueDate) {
            self::el($x, 'DataScadenzaPagamento', (string) $invoice->dueDate);
        }
        self::el($x, 'ImportoPagamento', self::money($total));
        if (($invoice->paymentMethod ?? 'bonifico') === 'bonifico' && $company->iban) {
            self::el($x, 'IBAN', strtoupper(str_replace(' ', '', $company->iban)));
        }
        $x->endElement();
        $x->endElement();
        $x->endElement();

        $x->endElement();
        $x->endDocument();

        return ['xml' => $x->outputMemory(), 'fileName' => "IT{$companyVat}_{$progressive}.xml"];
    }

    /** @return string[] cosa manca per poter fare la fattura elettronica */
    public static function problems(Invoice $invoice, CompanyProfile $company, array $customer): array
    {
        $missing = [];
        foreach (['companyName' => 'ragione sociale', 'vatNumber' => 'partita IVA', 'address' => 'indirizzo', 'postalCode' => 'CAP', 'city' => 'comune', 'province' => 'provincia'] as $field => $label) {
            if (trim((string) $company->$field) === '') {
                $missing[] = "{$label} dell'azienda";
            }
        }
        if (empty($customer['vat']) && empty($customer['taxCode'])) {
            $missing[] = 'partita IVA o codice fiscale del cliente';
        }
        foreach (['address' => 'indirizzo', 'postalCode' => 'CAP', 'city' => 'comune'] as $field => $label) {
            if (trim((string) ($customer[$field] ?? '')) === '') {
                $missing[] = "{$label} del cliente";
            }
        }
        if ($invoice->invoiceNumber === null) {
            $missing[] = 'numero (la fattura va prima emessa)';
        }

        return $missing;
    }

    private static function address(\XMLWriter $x, string $street, string $cap, string $city, string $province, string $country): void
    {
        $x->startElement('Sede');
        self::el($x, 'Indirizzo', mb_substr($street, 0, 60));
        self::el($x, 'CAP', str_pad(self::digits($cap), 5, '0', STR_PAD_LEFT));
        self::el($x, 'Comune', mb_substr($city, 0, 60));
        if ($province !== '') {
            self::el($x, 'Provincia', strtoupper(substr($province, 0, 2)));
        }
        self::el($x, 'Nazione', strtoupper($country ?: 'IT'));
        $x->endElement();
    }

    private static function el(\XMLWriter $x, string $name, string $value): void
    {
        $x->writeElement($name, $value);
    }

    private static function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private static function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }
}

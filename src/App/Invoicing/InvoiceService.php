<?php

declare(strict_types=1);

namespace App\Invoicing;

use App\Billing\PaymentPlanService;
use App\Connector\ConnectorException;
use App\Connector\ConnectorRegistry;
use App\Core\Container;
use App\Model\CompanyProfile;
use App\Model\Customer;
use App\Model\Installment;
use App\Model\Invoice;
use App\Package\PackageCatalogService;
use App\Payments\PaymentService;
use App\Repository\CompanyProfileRepository;
use App\Repository\CustomerRepository;
use App\Repository\InstallmentRepository;
use App\Repository\InvoiceRepository;
use App\Repository\MunicipalityRepository;

/**
 * La vita di una fattura: bozza (anche creata da un incasso dello
 * scadenzario, con un clic), emissione (numero progressivo dell'anno, IVA
 * e totale calcolati), XML FatturaPA, invio allo SdI con il fornitore
 * collegato, pagata.
 *
 * Una fattura emessa non si rinumera ne' si cancella: si annulla (e serve
 * una nota di credito, fuori da qui).
 */
final class InvoiceService
{
    /** Codici dei fornitori per lo SdI, nell'ordine in cui si preferiscono. */
    public const PROVIDERS = ['fatture_in_cloud', 'aruba_invoices'];

    private InvoiceRepository $invoices;
    private CustomerRepository $customers;
    private CompanyProfileRepository $profiles;
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->invoices = $container->get(InvoiceRepository::class);
        $this->customers = $container->get(CustomerRepository::class);
        $this->profiles = $container->get(CompanyProfileRepository::class);
    }

    /** Il fornitore collegato per lo SdI, se c'e'. */
    public function provider(): ?InvoiceProviderInterface
    {
        if (!in_array('connectors', PackageCatalogService::installedNames(), true)) {
            return null;
        }
        $registry = $this->container->get(ConnectorRegistry::class);
        foreach (self::PROVIDERS as $code) {
            $connector = $registry->has($code) ? $registry->get($code) : null;
            if ($connector instanceof InvoiceProviderInterface && $connector->isConnected()) {
                return $connector;
            }
        }

        return null;
    }

    /** La fattura (o pro forma) di un incasso: quella che c'e' gia', o una bozza nuova con i suoi dati. */
    public function createFromInstallment(int $installmentId, string $type = 'fattura'): Invoice
    {
        $installments = $this->container->get(InstallmentRepository::class);
        $installment = $installments->find($installmentId);
        if (!$installment instanceof Installment) {
            throw new InvoicingException('Incasso non trovato.');
        }
        if ($installment->invoiceId !== null && ($existing = $this->invoices->find((int) $installment->invoiceId)) instanceof Invoice) {
            return $existing;
        }

        $customer = $this->customers->find((int) $installment->customerId);
        $rate = $this->container->get(PaymentService::class)->taxRate($installment);
        $id = $this->invoices->insert([
            'document_type' => $type === 'pro forma' ? 'pro forma' : 'fattura',
            'customer_id' => $installment->customerId,
            'issue_date' => date('Y-m-d'),
            // Una scadenza gia' passata (incasso arretrato o pagato) non va in fattura: si paga a vista.
            'due_date' => max((string) $installment->dueDate, date('Y-m-d')),
            'stage' => 'bozza',
            'description' => $installment->description,
            'subtotal' => $installment->amount,
            'tax_rate_percent' => $rate,
            'payment_method' => $installment->paymentMethod ?: ($customer?->preferredPaymentMethod ?: 'bonifico'),
            'customer_service_id' => $installment->customerServiceId,
            'installment_id' => $installment->id,
            'sdi_status' => $type === 'pro forma' ? null : 'da inviare',
        ]);
        $installments->update($installmentId, ['invoice_id' => $id]);

        return $this->invoices->find($id);
    }

    /** Bozza -> emessa: numero, IVA e totale. */
    public function issue(int $invoiceId): Invoice
    {
        $invoice = $this->invoices->find($invoiceId);
        if (!$invoice instanceof Invoice) {
            throw new InvoicingException('Fattura non trovata.');
        }
        if ($invoice->stage !== 'bozza') {
            throw new InvoicingException('La fattura e\' gia\' emessa.');
        }
        if ((float) $invoice->subtotal <= 0) {
            throw new InvoicingException('Manca l\'imponibile.');
        }

        $date = $invoice->issueDate ?: date('Y-m-d');
        $year = (int) substr($date, 0, 4);
        // Fattura di un incasso gia' arrivato: nasce pagata.
        $installment = $invoice->installmentId !== null ? $this->container->get(InstallmentRepository::class)->find((int) $invoice->installmentId) : null;
        $alreadyPaid = $installment instanceof Installment && $installment->stage === 'incassato';
        $proForma = $invoice->isProForma();
        $seq = $this->invoices->nextSequence($year, $proForma);
        $rate = $invoice->taxRatePercent !== null ? (float) $invoice->taxRatePercent : $this->defaultRate();
        $tax = round((float) $invoice->subtotal * $rate / 100, 2);
        $this->invoices->update($invoiceId, [
            'issue_date' => $date,
            'number_year' => $year,
            'number_seq' => $seq,
            'invoice_number' => ($proForma ? 'PF' : '') . "{$year}/{$seq}",
            'tax_rate_percent' => $rate,
            'tax_amount' => $tax,
            'total' => round((float) $invoice->subtotal + $tax, 2),
            'stage' => $alreadyPaid && !$proForma ? 'pagata' : 'emessa',
            'due_date' => $invoice->dueDate !== null && $invoice->dueDate < $date ? $date : $invoice->dueDate,
            // La pro forma non ha valore fiscale: non va allo SdI.
            'sdi_status' => $proForma ? null : 'da inviare',
        ]);

        return $this->invoices->find($invoiceId);
    }

    /**
     * Dalla pro forma alla fattura vera (di solito a incasso avvenuto): una
     * bozza con gli stessi dati, l'incasso passa alla fattura, la pro forma
     * resta in archivio come annullata.
     */
    public function convertProForma(int $invoiceId): Invoice
    {
        $proForma = $this->invoices->find($invoiceId);
        if (!$proForma instanceof Invoice || !$proForma->isProForma() || $proForma->stage !== 'emessa') {
            throw new InvoicingException('Si trasforma solo una pro forma emessa.');
        }
        $id = $this->invoices->insert([
            'document_type' => 'fattura',
            'customer_id' => $proForma->customerId,
            'issue_date' => date('Y-m-d'),
            'due_date' => max((string) $proForma->dueDate, date('Y-m-d')),
            'stage' => 'bozza',
            'description' => $proForma->description,
            'subtotal' => $proForma->subtotal,
            'tax_rate_percent' => $proForma->taxRatePercent,
            'payment_method' => $proForma->paymentMethod,
            'customer_service_id' => $proForma->customerServiceId,
            'installment_id' => $proForma->installmentId,
            'sdi_status' => 'da inviare',
        ]);
        $this->invoices->update($invoiceId, ['stage' => 'annullata', 'installment_id' => null]);
        if ($proForma->installmentId !== null) {
            $this->container->get(InstallmentRepository::class)->update((int) $proForma->installmentId, ['invoice_id' => $id]);
        }

        return $this->invoices->find($id);
    }

    /** @return array{xml:string, fileName:string} */
    public function xml(int $invoiceId): array
    {
        [$invoice, $company, $customer] = $this->parts($invoiceId);

        return FatturaPaXml::build($invoice, $company, $customer);
    }

    /** @return string[] cosa manca per la fattura elettronica (vuoto: si puo' fare) */
    public function problems(Invoice $invoice): array
    {
        $company = $this->profiles->current() ?? new CompanyProfile();
        $customer = $this->customers->find((int) $invoice->customerId);

        return FatturaPaXml::problems($invoice, $company, $customer instanceof Customer ? $this->customerData($customer) : []);
    }

    /** Invio allo SdI con il fornitore collegato. */
    public function send(int $invoiceId): string
    {
        $provider = $this->provider();
        if ($provider === null) {
            throw new InvoicingException('Nessun fornitore collegato per lo SdI: collega Fatture in Cloud o Aruba dalla Configurazione, oppure scarica l\'XML e caricalo a mano.');
        }
        [$invoice, $company, $customer] = $this->parts($invoiceId);
        if ($invoice->stage === 'bozza' || $invoice->stage === 'annullata') {
            throw new InvoicingException('Si invia solo una fattura emessa.');
        }
        if (in_array($invoice->sdiStatus, ['inviata', 'consegnata'], true)) {
            throw new InvoicingException('Questa fattura e\' gia\' stata inviata.');
        }
        $built = FatturaPaXml::build($invoice, $company, $customer);
        try {
            $result = $provider->sendInvoice($invoice, $built['xml'], $built['fileName'], $customer);
        } catch (ConnectorException $e) {
            throw new InvoicingException($e->getMessage());
        }
        $this->invoices->update($invoiceId, [
            'sdi_status' => $result['status'],
            'sdi_provider' => $provider->code(),
            'sdi_reference' => $result['reference'],
            'sent_at' => date('Y-m-d H:i:s'),
        ]);

        return 'Inviata allo SdI con ' . $provider->label() . '.';
    }

    /** Pagata: anche l'incasso collegato risulta incassato. */
    public function markPaid(int $invoiceId): void
    {
        $invoice = $this->invoices->find($invoiceId);
        if (!$invoice instanceof Invoice || $invoice->stage !== 'emessa') {
            throw new InvoicingException('Si segna pagata solo una fattura emessa.');
        }
        $this->invoices->update($invoiceId, ['stage' => 'pagata']);
        if ($invoice->installmentId !== null) {
            $installment = $this->container->get(InstallmentRepository::class)->find((int) $invoice->installmentId);
            if ($installment instanceof Installment && $installment->stage === 'da incassare') {
                $this->container->get(PaymentPlanService::class)->markPaid((int) $installment->id, date('Y-m-d'), $invoice->paymentMethod);
            }
        }
    }

    public function defaultRate(): float
    {
        $profile = $this->profiles->current();

        return $profile?->defaultTaxRate !== null ? (float) $profile->defaultTaxRate : 22.0;
    }

    /** @return array{0:Invoice, 1:CompanyProfile, 2:array} */
    private function parts(int $invoiceId): array
    {
        $invoice = $this->invoices->find($invoiceId);
        if (!$invoice instanceof Invoice) {
            throw new InvoicingException('Fattura non trovata.');
        }
        $company = $this->profiles->current();
        if ($company === null) {
            throw new InvoicingException('Mancano i dati dell\'azienda che emette le fatture: compilali in Fatture, "Dati azienda".');
        }
        $customer = $this->customers->find((int) $invoice->customerId);
        if (!$customer instanceof Customer) {
            throw new InvoicingException('Cliente della fattura non trovato.');
        }

        return [$invoice, $company, $this->customerData($customer)];
    }

    /** Il cliente come lo vuole l'XML: P.IVA (11 cifre) o codice fiscale (16 caratteri) dallo stesso campo. */
    private function customerData(Customer $customer): array
    {
        $fiscal = strtoupper(str_replace(' ', '', (string) $customer->vatNumber));
        $fiscal = preg_replace('/^IT(?=\d{11}$)/', '', $fiscal);
        $isVat = preg_match('/^\d{11}$/', $fiscal) === 1;
        $place = $customer->municipalityId !== null ? $this->container->get(MunicipalityRepository::class)->findWithProvince((int) $customer->municipalityId) : null;

        return [
            'name' => $customer->displayName(),
            'vat' => $isVat ? $fiscal : null,
            'taxCode' => !$isVat && strlen($fiscal) === 16 ? $fiscal : null,
            'address' => $customer->address,
            'postalCode' => $customer->postalCode,
            'city' => $place['name'] ?? null,
            'province' => $place['province_code'] ?? null,
            'sdiCode' => $customer->sdiCode,
            'pec' => $customer->pec,
        ];
    }
}

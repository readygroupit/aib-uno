<?php

declare(strict_types=1);

namespace App\Payments;

use App\Billing\PaymentPlanService;
use App\Connector\ConnectorException;
use App\Connector\ConnectorRegistry;
use App\Connector\PayPalConnector;
use App\Connector\StripeConnector;
use App\Core\Container;
use App\Model\Customer;
use App\Model\Installment;
use App\Package\PackageCatalogService;
use App\Repository\CompanyProfileRepository;
use App\Repository\CustomerRepository;
use App\Repository\CustomerServiceRepository;
use App\Repository\InstallmentRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ServiceRepository;

/**
 * Incassi online, dal punto di vista del gestionale:
 * - un link per pagare una rata o un rinnovo (Stripe con carta o SEPA,
 *   oppure PayPal), scelto in base al metodo preferito dal cliente;
 * - il pagamento automatico: il cliente salva carta o mandato SEPA una
 *   volta (Stripe), poi rate e rinnovi si addebitano alla scadenza
 *   (chargeDue(), dal job giornaliero);
 * - le conferme dei fornitori (webhook Stripe, ritorno da PayPal) segnano
 *   l'incasso come pagato, e la fattura collegata come pagata.
 *
 * Gli importi dello scadenzario sono imponibili: al cliente si fa pagare
 * il lordo (IVA compresa), o il totale della fattura se c'e' gia'.
 */
final class PaymentService
{
    private InstallmentRepository $installments;
    private CustomerRepository $customers;
    private PaymentPlanService $plans;
    private Container $container;
    private ?StripeConnector $stripe = null;
    private ?PayPalConnector $paypal = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->installments = $container->get(InstallmentRepository::class);
        $this->customers = $container->get(CustomerRepository::class);
        $this->plans = $container->get(PaymentPlanService::class);
        if (in_array('connectors', PackageCatalogService::installedNames(), true)) {
            $registry = $container->get(ConnectorRegistry::class);
            $stripe = $registry->has('stripe') ? $registry->get('stripe') : null;
            $paypal = $registry->has('paypal') ? $registry->get('paypal') : null;
            $this->stripe = $stripe instanceof StripeConnector && $stripe->isConnected() ? $stripe : null;
            $this->paypal = $paypal instanceof PayPalConnector && $paypal->isConnected() ? $paypal : null;
        }
    }

    public function hasOnlinePayments(): bool
    {
        return $this->stripe !== null || $this->paypal !== null;
    }

    public function canAutoPay(): bool
    {
        return $this->stripe !== null;
    }

    /** @return list<array{code:string,label:string}> */
    public function available(): array
    {
        return array_values(array_filter([
            $this->stripe !== null ? ['code' => 'stripe', 'label' => 'Carta o addebito SEPA'] : null,
            $this->paypal !== null ? ['code' => 'paypal', 'label' => 'PayPal'] : null,
        ]));
    }

    /** Quanto paga il cliente: il totale della fattura, o l'importo con l'IVA. */
    public function grossAmount(Installment $installment): float
    {
        if ($installment->invoiceId !== null && in_array('invoices', PackageCatalogService::installedNames(), true)) {
            $invoice = $this->container->get(InvoiceRepository::class)->find((int) $installment->invoiceId);
            if ($invoice !== null && $invoice->total !== null) {
                return (float) $invoice->total;
            }
        }

        return round((float) $installment->amount * (1 + $this->taxRate($installment) / 100), 2);
    }

    public function taxRate(Installment $installment): float
    {
        if ($installment->customerServiceId !== null) {
            $sold = $this->container->get(CustomerServiceRepository::class)->find((int) $installment->customerServiceId);
            if ($sold !== null && $sold->serviceId !== null) {
                $service = $this->container->get(ServiceRepository::class)->find((int) $sold->serviceId);
                if ($service !== null && $service->taxRatePercent !== null) {
                    return (float) $service->taxRatePercent;
                }
            }
        }
        if (in_array('invoices', PackageCatalogService::installedNames(), true)) {
            $profile = $this->container->get(CompanyProfileRepository::class)->current();
            if ($profile !== null && $profile->defaultTaxRate !== null) {
                return (float) $profile->defaultTaxRate;
            }
        }

        return 22.0;
    }

    /**
     * Link di pagamento per un incasso. Fornitore: quello chiesto, oppure
     * quello del metodo preferito dal cliente, oppure il primo collegato.
     *
     * @return array{url:string, provider:string}
     */
    public function createLink(int $installmentId, ?string $provider, string $baseUrl, ?string $returnPath = null): array
    {
        $installment = $this->installments->find($installmentId);
        if (!$installment instanceof Installment || $installment->stage !== 'da incassare') {
            throw new ConnectorException('Questo incasso non e\' da incassare.');
        }
        $customer = $this->customers->find((int) $installment->customerId);
        if (!$customer instanceof Customer) {
            throw new ConnectorException('Cliente non trovato.');
        }

        $provider ??= $customer->preferredPaymentMethod === 'PayPal' ? 'paypal' : 'stripe';
        $gateway = $provider === 'paypal' ? ($this->paypal ?? $this->stripe) : ($this->stripe ?? $this->paypal);
        if ($gateway === null) {
            throw new ConnectorException('Nessun pagamento online collegato: collega Stripe o PayPal dalla Configurazione.');
        }

        $back = rtrim($baseUrl, '/') . ($returnPath ?? '/scadenzario');
        $separator = str_contains($back, '?') ? '&' : '?';
        $result = $gateway->checkout([
            'reference' => 'inst-' . $installment->id,
            'description' => (string) $installment->description,
            'amountCents' => (int) round($this->grossAmount($installment) * 100),
            'email' => $customer->email,
            'customerName' => $customer->displayName(),
            'customerRef' => $gateway instanceof StripeConnector ? $customer->stripeCustomerId : null,
            'successUrl' => $gateway instanceof PayPalConnector
                ? rtrim($baseUrl, '/') . PayPalConnector::RETURN_PATH . '?ritorno=' . rawurlencode($returnPath ?? '/scadenzario')
                : $back . $separator . 'pagamento=ok',
            'cancelUrl' => $back . $separator . 'pagamento=annullato',
            'methods' => $customer->preferredPaymentMethod === 'addebito SEPA' ? ['sepa_debit', 'card'] : ['card', 'sepa_debit'],
        ]);

        $this->installments->update($installmentId, ['payment_link_url' => $result['url'], 'payment_provider' => $gateway->code()]);
        if ($gateway instanceof StripeConnector && $result['customerRef'] !== null && $customer->stripeCustomerId === null) {
            $this->customers->update((int) $customer->id, ['stripe_customer_id' => $result['customerRef']]);
        }

        return ['url' => $result['url'], 'provider' => $gateway->code()];
    }

    /** Pagina Stripe in cui il cliente salva carta o mandato SEPA per gli addebiti automatici. */
    public function autoPaySetupLink(int $customerId, string $baseUrl, string $returnPath): string
    {
        if ($this->stripe === null) {
            throw new ConnectorException('Il pagamento automatico richiede Stripe collegato.');
        }
        $customer = $this->customers->find($customerId);
        if (!$customer instanceof Customer) {
            throw new ConnectorException('Cliente non trovato.');
        }
        $back = rtrim($baseUrl, '/') . $returnPath;
        $separator = str_contains($back, '?') ? '&' : '?';
        $result = $this->stripe->setupAutoPay($customer->displayName(), $customer->email, $customer->stripeCustomerId, 'cust-' . $customer->id, $back . $separator . 'automatico=ok', $back . $separator . 'automatico=annullato');
        if ($customer->stripeCustomerId === null) {
            $this->customers->update($customerId, ['stripe_customer_id' => $result['customerRef']]);
        }

        return $result['url'];
    }

    /**
     * Notifica di Stripe gia' verificata.
     *
     * @return string cosa e' successo (per il log)
     */
    public function handleStripeEvent(array $event): string
    {
        $object = $event['data']['object'] ?? [];
        $reference = (string) ($object['metadata']['reference'] ?? $object['client_reference_id'] ?? '');

        switch ($event['type'] ?? '') {
            case 'checkout.session.completed':
                if (($object['mode'] ?? '') === 'setup' && str_starts_with($reference, 'cust-') && $this->stripe !== null) {
                    $method = isset($object['setup_intent']) ? $this->stripe->paymentMethodFromSetupIntent((string) $object['setup_intent']) : null;
                    if ($method !== null) {
                        $this->customers->update((int) substr($reference, 5), [
                            'payment_mandate' => $method,
                            'stripe_customer_id' => $object['customer'] ?? null,
                            'auto_pay' => 1,
                        ]);

                        return 'metodo salvato per ' . $reference;
                    }
                }
                // Con la carta e' gia' pagato; con il SEPA si aspetta payment_intent.succeeded.
                if (($object['payment_status'] ?? '') === 'paid' && str_starts_with($reference, 'inst-')) {
                    $this->markPaid((int) substr($reference, 5), 'stripe', (string) ($object['payment_intent'] ?? $object['id'] ?? ''));

                    return 'pagato ' . $reference;
                }

                return 'sessione completata, in attesa';
            case 'payment_intent.succeeded':
                if (str_starts_with($reference, 'inst-')) {
                    $this->markPaid((int) substr($reference, 5), 'stripe', (string) ($object['id'] ?? ''));

                    return 'pagato ' . $reference;
                }

                return 'ignorato';
            case 'payment_intent.payment_failed':
                if (str_starts_with($reference, 'inst-')) {
                    $this->installments->update((int) substr($reference, 5), [
                        'notes' => 'Addebito non riuscito il ' . date('d/m/Y') . ': ' . ($object['last_payment_error']['message'] ?? 'motivo non indicato'),
                        'payment_reference' => null,
                    ]);

                    return 'addebito fallito ' . $reference;
                }

                return 'ignorato';
            default:
                return 'evento non gestito';
        }
    }

    /** Ritorno da PayPal: si incassa l'ordine approvato. */
    public function capturePayPal(string $orderId): bool
    {
        if ($this->paypal === null) {
            return false;
        }
        $result = $this->paypal->capture($orderId);
        if ($result['completed'] && str_starts_with((string) $result['reference'], 'inst-')) {
            $this->markPaid((int) substr((string) $result['reference'], 5), 'paypal', (string) ($result['captureId'] ?? $orderId));

            return true;
        }

        return false;
    }

    /**
     * Addebiti automatici delle scadenze arrivate, per chi ha attivato il
     * pagamento automatico e salvato un metodo. Un incasso gia' tentato
     * (payment_reference) non si ritenta da solo.
     *
     * @return array{charged:int, pending:int, failed:int}
     */
    public function chargeDue(string $today): array
    {
        $outcome = ['charged' => 0, 'pending' => 0, 'failed' => 0];
        if ($this->stripe === null) {
            return $outcome;
        }
        foreach ($this->installments->findOpenUntil($today) as $installment) {
            if ($installment->paymentReference !== null) {
                continue;
            }
            $customer = $this->customers->find((int) $installment->customerId);
            if (!$customer instanceof Customer || (int) $customer->autoPay !== 1 || $customer->paymentMandate === null || $customer->stripeCustomerId === null) {
                continue;
            }
            try {
                $result = $this->stripe->charge($customer->stripeCustomerId, $customer->paymentMandate, (int) round($this->grossAmount($installment) * 100), (string) $installment->description, 'inst-' . $installment->id);
            } catch (ConnectorException $e) {
                $outcome['failed']++;
                continue;
            }
            if ($result['status'] === 'succeeded') {
                $this->markPaid((int) $installment->id, 'stripe', $result['id']);
                $outcome['charged']++;
            } elseif (in_array($result['status'], ['processing', 'requires_capture'], true)) {
                $this->installments->update((int) $installment->id, ['payment_provider' => 'stripe', 'payment_reference' => $result['id'], 'notes' => 'Addebito automatico in corso (' . date('d/m/Y') . ')']);
                $outcome['pending']++;
            } else {
                $this->installments->update((int) $installment->id, ['notes' => 'Addebito automatico non riuscito il ' . date('d/m/Y') . ': serve un pagamento manuale.']);
                $outcome['failed']++;
            }
        }

        return $outcome;
    }

    public function markPaid(int $installmentId, string $provider, string $reference): void
    {
        $installment = $this->installments->find($installmentId);
        if (!$installment instanceof Installment || $installment->stage === 'incassato') {
            return;
        }
        $method = $provider === 'paypal' ? 'PayPal' : ($installment->paymentMethod === 'addebito SEPA' ? 'addebito SEPA' : 'carta');
        $this->plans->markPaid($installmentId, date('Y-m-d'), $method);
        $this->installments->update($installmentId, ['payment_provider' => $provider, 'payment_reference' => $reference]);
    }
}

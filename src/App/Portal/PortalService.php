<?php

declare(strict_types=1);

namespace App\Portal;

use App\Billing\AccountOverviewService;
use App\Core\Container;
use App\Editorial\EditorialService;
use App\Model\Customer;
use App\Model\CustomerService;
use App\Model\EditorialPost;
use App\Model\Installment;
use App\Model\Invoice;
use App\Model\Quote;
use App\Package\PackageCatalogService;
use App\Payments\PaymentService;
use App\Quotes\QuoteOverviewService;
use App\Quotes\QuoteService;
use App\Repository\CompanyProfileRepository;
use App\Repository\CustomerRepository;
use App\Repository\CustomerServiceRepository;
use App\Repository\EditorialPostRepository;
use App\Repository\InstallmentRepository;
use App\Repository\InvoiceRepository;
use App\Repository\QuoteRepository;
use App\Support\ItalianFormat as F;

/**
 * L'area del cliente: un link personale (customers.portal_token), niente
 * password. Il cliente vede i suoi servizi e i rinnovi, cosa deve pagare
 * (e paga online), come paga e se vuole l'addebito automatico, le
 * fatture, e approva i contenuti del piano editoriale.
 *
 * Chi ha il link entra: il token e' lungo e casuale, e si rigenera dalla
 * scheda cliente se finisce nelle mani sbagliate. Qui si leggono e si
 * scrivono solo i dati di QUEL cliente: ogni azione ricontrolla che la
 * riga sia sua.
 */
final class PortalService
{
    private CustomerRepository $customers;
    private Container $container;
    private string $today;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->customers = $container->get(CustomerRepository::class);
        $this->today = date('Y-m-d');
    }

    public function customerByToken(string $token): ?Customer
    {
        // Token corto o con caratteri strani: non si chiede nemmeno al database.
        if (preg_match('/^[a-f0-9]{40,64}$/', $token) !== 1) {
            return null;
        }

        return $this->customers->findByPortalToken($token);
    }

    /** Il link del cliente; con $renew un token nuovo (il vecchio smette di funzionare). */
    public function link(int $customerId, string $baseUrl, bool $renew = false): string
    {
        $customer = $this->customers->find($customerId);
        if (!$customer instanceof Customer) {
            throw new \InvalidArgumentException('Cliente non trovato.');
        }
        $token = $customer->portalToken;
        if ($token === null || $renew) {
            $token = bin2hex(random_bytes(24));
            $this->customers->update($customerId, ['portal_token' => $token]);
        }

        return rtrim($baseUrl, '/') . '/area/' . $token;
    }

    public function view(Customer $customer): array
    {
        $installed = PackageCatalogService::installedNames();
        $has = static fn (string $name) => in_array($name, $installed, true);
        $payments = $has('installments') ? $this->container->get(PaymentService::class) : null;
        $company = $has('invoices') ? $this->container->get(CompanyProfileRepository::class)->current() : null;

        return [
            'customer' => $customer->displayName(),
            'agency' => $company?->companyName ?: APP_NAME,
            'agencyEmail' => $company?->email,
            'agencyPhone' => $company?->phone,
            'posts' => $has('editorial') ? $this->posts($customer) : [],
            'quotes' => $has('quotes') ? $this->quotes($customer) : [],
            'services' => $has('customer_services') ? $this->services($customer) : [],
            'due' => $has('installments') ? $this->due($customer, $payments) : [],
            'paid' => $has('installments') ? $this->paid($customer) : [],
            'invoices' => $has('invoices') ? $this->invoices($customer) : [],
            'payment' => [
                'method' => $customer->preferredPaymentMethod ?: 'bonifico',
                'methods' => ['bonifico', 'carta', 'addebito SEPA', 'PayPal'],
                'autoPay' => (int) $customer->autoPay === 1 && $customer->paymentMandate !== null,
                'canAutoPay' => $payments?->canAutoPay() ?? false,
                'online' => $payments?->hasOnlinePayments() ?? false,
                'iban' => $company?->iban,
            ],
        ];
    }

    /** Il cliente approva un contenuto: e' pronto da programmare. */
    public function approve(Customer $customer, int $postId): string
    {
        $post = $this->ownPost($customer, $postId);
        $this->container->get(EditorialPostRepository::class)->update($postId, [
            'stage' => 'approvato',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_via' => 'area cliente',
        ]);
        $this->container->get(EditorialService::class)->syncTask($postId);

        return "Approvato: \u{ab}{$post->title}\u{bb}. Grazie!";
    }

    /** Tutto quello che aspetta il cliente, approvato in un clic. */
    public function approveAll(Customer $customer): string
    {
        $count = $this->container->get(EditorialService::class)->approveAllByClient((int) $customer->id);

        return $count > 0 ? ($count === 1 ? 'Approvato 1 contenuto' : "Approvati {$count} contenuti") . '. Grazie!' : 'Niente da approvare.';
    }

    /** Il cliente accetta una proposta: diventa servizi e scadenze (QuoteService::accept()). */
    public function acceptQuote(Customer $customer, int $quoteId): string
    {
        $quote = $this->container->get(QuoteRepository::class)->find($quoteId);
        if (!$quote instanceof Quote || (int) $quote->customerId !== (int) $customer->id || $quote->stage !== 'inviato') {
            throw new \InvalidArgumentException('Questa proposta non e\' piu\' da accettare.');
        }
        $this->container->get(QuoteService::class)->accept($quoteId, 'area cliente');

        return "Grazie! Abbiamo ricevuto l'accettazione di \u{ab}{$quote->title}\u{bb}: ti contattiamo per partire.";
    }

    /** Il cliente chiede modifiche: torna all'agenzia con il suo commento. */
    public function requestChanges(Customer $customer, int $postId, string $comment): string
    {
        $comment = trim($comment);
        if ($comment === '') {
            throw new \InvalidArgumentException('Scrivi cosa vorresti cambiare.');
        }
        $post = $this->ownPost($customer, $postId);
        $this->container->get(EditorialPostRepository::class)->update($postId, [
            'stage' => 'da modificare',
            'client_feedback' => mb_substr($comment, 0, 2000),
            'revision_count' => (int) $post->revisionCount + 1,
        ]);
        $this->container->get(EditorialService::class)->syncTask($postId);

        return "Ricevuto: sistemiamo \u{ab}{$post->title}\u{bb} e te lo rimandiamo.";
    }

    public function setPaymentMethod(Customer $customer, string $method): string
    {
        if (!in_array($method, ['bonifico', 'carta', 'addebito SEPA', 'PayPal'], true)) {
            throw new \InvalidArgumentException('Metodo non valido.');
        }
        $this->customers->update((int) $customer->id, ['preferred_payment_method' => $method]);

        return "D'ora in poi ti proponiamo di pagare con {$method}.";
    }

    public function disableAutoPay(Customer $customer): string
    {
        $this->customers->update((int) $customer->id, ['auto_pay' => 0]);

        return 'Addebito automatico disattivato: le prossime scadenze le paghi tu, con il link o con il bonifico.';
    }

    /** L'incasso, se e' di questo cliente e ancora da pagare. */
    public function ownOpenInstallment(Customer $customer, int $installmentId): ?Installment
    {
        $installment = $this->container->get(InstallmentRepository::class)->find($installmentId);

        return $installment instanceof Installment && (int) $installment->customerId === (int) $customer->id && $installment->stage === 'da incassare' ? $installment : null;
    }

    private function ownPost(Customer $customer, int $postId): EditorialPost
    {
        $post = $this->container->get(EditorialPostRepository::class)->find($postId);
        if (!$post instanceof EditorialPost || (int) $post->customerId !== (int) $customer->id || $post->stage !== 'da approvare') {
            throw new \InvalidArgumentException('Questo contenuto non e\' piu\' da approvare.');
        }

        return $post;
    }

    private function posts(Customer $customer): array
    {
        return array_map(fn (EditorialPost $p) => [
            'id' => (int) $p->id,
            'title' => (string) $p->title,
            'channel' => (string) $p->channel,
            'format' => (string) $p->format,
            'when' => F::longDate($p->publishDate) . ($p->publishTime ? ' alle ' . substr($p->publishTime, 0, 5) : ''),
            'caption' => $p->caption,
            'media' => $p->mediaNotes,
        ], $this->container->get(EditorialPostRepository::class)->findForCustomerInStage((int) $customer->id, 'da approvare'));
    }

    private function quotes(Customer $customer): array
    {
        $overview = $this->container->get(QuoteOverviewService::class);

        return array_map(static function (Quote $q) use ($overview) {
            $detail = $overview->detail($q, false);

            return [
                'id' => (int) $q->id,
                'number' => (string) $q->quoteNumber,
                'title' => (string) $q->title,
                'intro' => $q->intro,
                'validUntil' => $detail['validUntil'],
                'lines' => $detail['lines'],
                'totals' => $detail['totals'],
                'paymentTerms' => $q->paymentTerms,
                'conditions' => $q->conditions,
                'revisions' => $q->includedRevisions,
            ];
        }, $this->container->get(QuoteRepository::class)->findForCustomerInStage((int) $customer->id, 'inviato'));
    }

    private function services(Customer $customer): array
    {
        $overview = $this->container->get(AccountOverviewService::class);
        $active = array_filter(
            $this->container->get(CustomerServiceRepository::class)->findByCustomer((int) $customer->id),
            static fn (CustomerService $s) => in_array($s->stage, ['attivo', 'sospeso'], true)
        );

        return array_values(array_map(fn (CustomerService $s) => [
            'title' => (string) $s->title,
            'price' => $overview->priceLabel($s) . ' + IVA',
            'stage' => (string) $s->stage,
            'renewal' => $s->isRecurring() && $s->renewalDate !== null
                ? ((int) $s->autoRenew === 1 ? 'si rinnova da solo il ' : 'scade il ') . F::longDate($s->renewalDate)
                : ($s->endDate !== null ? 'fino al ' . F::longDate($s->endDate) : null),
        ], $active));
    }

    private function due(Customer $customer, ?PaymentService $payments): array
    {
        $open = array_filter(
            $this->container->get(InstallmentRepository::class)->findByCustomer((int) $customer->id),
            static fn (Installment $i) => $i->stage === 'da incassare'
        );

        return array_values(array_map(function (Installment $i) use ($payments) {
            $days = F::daysFrom((string) $i->dueDate, $this->today);

            return [
                'id' => (int) $i->id,
                'description' => (string) $i->description,
                'amount' => F::euro($payments !== null ? $payments->grossAmount($i) : (float) $i->amount),
                'due' => F::longDate($i->dueDate),
                'when' => $days < 0 ? 'scaduto da ' . F::plural(-$days, 'giorno', 'giorni') : F::relativeDays((string) $i->dueDate, $this->today),
                'late' => $days < 0,
                'processing' => $i->paymentReference !== null,
            ];
        }, array_slice($open, 0, 12)));
    }

    private function paid(Customer $customer): array
    {
        $paid = array_filter(
            $this->container->get(InstallmentRepository::class)->findByCustomer((int) $customer->id),
            static fn (Installment $i) => $i->stage === 'incassato'
        );
        usort($paid, static fn (Installment $a, Installment $b) => strcmp((string) $b->paidAt, (string) $a->paidAt));

        return array_map(static fn (Installment $i) => [
            'description' => (string) $i->description,
            'amount' => F::euro((float) $i->amount) . ' + IVA',
            'date' => F::longDate($i->paidAt),
        ], array_slice($paid, 0, 5));
    }

    private function invoices(Customer $customer): array
    {
        $issued = array_filter(
            $this->container->get(InvoiceRepository::class)->findByCustomer((int) $customer->id),
            static fn (Invoice $i) => in_array($i->stage, ['emessa', 'pagata'], true)
        );

        return array_values(array_map(static fn (Invoice $i) => [
            'number' => (string) $i->invoiceNumber,
            'date' => F::longDate($i->issueDate),
            'total' => F::euro((float) $i->total),
            'paid' => $i->stage === 'pagata',
        ], array_slice($issued, 0, 10)));
    }
}

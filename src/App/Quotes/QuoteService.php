<?php

declare(strict_types=1);

namespace App\Quotes;

use App\Billing\PaymentPlanService;
use App\Core\Container;
use App\Model\Customer;
use App\Model\Quote;
use App\Model\QuoteLine;
use App\Repository\CustomerRepository;
use App\Repository\CustomerServiceRepository;
use App\Repository\QuoteLineRepository;
use App\Repository\QuoteRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;

/**
 * La vita di un preventivo: bozza (numero P2026/N subito), approvazione
 * del titolare, invio, accettazione o rifiuto. Accettato, ogni riga
 * diventa un servizio venduto con il suo piano di pagamento (le scadenze
 * le crea PaymentPlanService): dal preventivo al contratto senza
 * ricopiare nulla.
 */
final class QuoteService
{
    private const PERIOD_PER_YEAR = ['mensile' => 12, 'trimestrale' => 4, 'semestrale' => 2, 'annuale' => 1];

    private QuoteRepository $quotes;
    private QuoteLineRepository $lines;
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->quotes = $container->get(QuoteRepository::class);
        $this->lines = $container->get(QuoteLineRepository::class);
    }

    /** Numero e valori di partenza (pagamento dal cliente) dopo la creazione. */
    public function initialize(int $quoteId): void
    {
        $quote = $this->quotes->find($quoteId);
        if (!$quote instanceof Quote) {
            return;
        }
        $year = (int) substr((string) ($quote->quoteDate ?: date('Y-m-d')), 0, 4);
        $seq = $this->quotes->nextSequence($year);
        $data = ['number_year' => $year, 'number_seq' => $seq, 'quote_number' => "P{$year}/{$seq}"];
        $customer = $this->container->get(CustomerRepository::class)->find((int) $quote->customerId);
        if ($quote->paymentTerms === null && $customer instanceof Customer) {
            $data['payment_terms'] = ($customer->preferredPaymentMethod ?: 'bonifico') . ($customer->paymentTermsDays ? " a {$customer->paymentTermsDays} giorni" : '');
        }
        $this->quotes->update($quoteId, $data);
    }

    /** Totali: una tantum e ricorrente riportato all'anno, con lo sconto. */
    public function recalc(int $quoteId): void
    {
        $quote = $this->quotes->find($quoteId);
        if (!$quote instanceof Quote) {
            return;
        }
        $factor = 1 - (float) $quote->discountPercent / 100;
        $oneOff = 0.0;
        $yearly = 0.0;
        foreach ($this->lines->findByQuote($quoteId) as $line) {
            if ($line->isRecurring()) {
                $yearly += $line->amount() * (self::PERIOD_PER_YEAR[$line->recurrence] ?? 1);
            } else {
                $oneOff += $line->amount();
            }
        }
        $this->quotes->update($quoteId, ['total_one_off' => round($oneOff * $factor, 2), 'total_yearly' => round($yearly * $factor, 2)]);
    }

    /**
     * Cosa manca prima di mandarlo: e' qui che nascono gli errori dei
     * preventivi (dati del cliente, condizioni, scadenza).
     *
     * @return string[]
     */
    public function problems(Quote $quote): array
    {
        $missing = [];
        $lines = $this->lines->findByQuote((int) $quote->id);
        if ($lines === []) {
            $missing[] = 'nessuna riga';
        }
        foreach ($lines as $line) {
            if ((float) $line->unitPrice <= 0) {
                $missing[] = "prezzo di \u{ab}{$line->description}\u{bb}";
            }
            if ($line->isRecurring() && $line->recurrence === null) {
                $missing[] = "ogni quanto si paga \u{ab}{$line->description}\u{bb}";
            }
        }
        if ($quote->validUntil === null) {
            $missing[] = 'scadenza del preventivo';
        }
        if (trim((string) $quote->paymentTerms) === '') {
            $missing[] = 'modalita\' di pagamento';
        }
        $customer = $this->container->get(CustomerRepository::class)->find((int) $quote->customerId);
        if ($customer instanceof Customer) {
            if (!$customer->email) {
                $missing[] = 'email del cliente';
            }
            if (!$customer->vatNumber) {
                $missing[] = 'partita IVA o codice fiscale del cliente';
            }
            if (!$customer->address) {
                $missing[] = 'indirizzo del cliente';
            }
        }

        return $missing;
    }

    /**
     * Il passo successivo.
     *
     * @return string cosa e' successo
     */
    public function advance(int $quoteId, string $step, int $userId, bool $canApprove, string $text = ''): string
    {
        $quote = $this->quotes->find($quoteId);
        if (!$quote instanceof Quote) {
            throw new \InvalidArgumentException('Preventivo non trovato.');
        }
        $now = date('Y-m-d H:i:s');
        switch ($step) {
            case 'approvazione':
                $this->assertNoBlockers($quote);
                if ($canApprove) {
                    $this->quotes->update($quoteId, ['stage' => 'approvato', 'approved_by' => $userId, 'approved_at' => $now]);

                    return 'Approvato: ora si puo\' mandare al cliente.';
                }
                $this->quotes->update($quoteId, ['stage' => 'da approvare']);

                return 'Mandato in approvazione: lo vede chi approva i preventivi.';
            case 'approva':
                if (!$canApprove) {
                    throw new \InvalidArgumentException('Non puoi approvare i preventivi.');
                }
                $this->quotes->update($quoteId, ['stage' => 'approvato', 'approved_by' => $userId, 'approved_at' => $now]);

                return 'Approvato: ora si puo\' mandare al cliente.';
            case 'rivedi':
                $this->quotes->update($quoteId, ['stage' => 'bozza']);

                return 'Tornato in bozza.';
            case 'inviato':
                if ($quote->stage !== 'approvato') {
                    throw new \InvalidArgumentException('Prima va approvato.');
                }
                $this->quotes->update($quoteId, ['stage' => 'inviato', 'sent_at' => $now]);

                return 'Segnato come inviato: il cliente puo\' accettarlo dalla sua area.';
            case 'accettato':
                $created = $this->accept($quoteId, trim($text) !== '' ? trim($text) . ', segnato da ' . $this->userName($userId) : 'segnato da ' . $this->userName($userId));

                return 'Accettato: ' . ($created === 1 ? 'creato 1 servizio venduto' : "creati {$created} servizi venduti") . ' con le scadenze.';
            case 'rifiutato':
                $this->quotes->update($quoteId, ['stage' => 'rifiutato', 'rejected_reason' => trim($text) !== '' ? mb_substr(trim($text), 0, 255) : null]);

                return 'Segnato come rifiutato.';
            default:
                throw new \InvalidArgumentException('Passo non valido.');
        }
    }

    /**
     * Accettazione: ogni riga diventa un servizio venduto (con il prezzo
     * scontato e il suo piano di pagamento), il cliente diventa cliente.
     *
     * @return int servizi creati
     */
    public function accept(int $quoteId, string $via): int
    {
        $quote = $this->quotes->find($quoteId);
        if (!$quote instanceof Quote || !in_array($quote->stage, ['approvato', 'inviato', 'scaduto'], true)) {
            throw new \InvalidArgumentException('Si accetta un preventivo approvato o inviato.');
        }
        $customers = $this->container->get(CustomerRepository::class);
        $customer = $customers->find((int) $quote->customerId);
        $sold = $this->container->get(CustomerServiceRepository::class);
        $plans = $this->container->get(PaymentPlanService::class);
        $catalog = $this->container->get(ServiceRepository::class);
        $factor = 1 - (float) $quote->discountPercent / 100;
        $method = $customer instanceof Customer && $customer->preferredPaymentMethod ? $customer->preferredPaymentMethod : 'bonifico';

        $created = 0;
        foreach ($this->lines->findByQuote($quoteId) as $line) {
            if ($line->customerServiceId !== null) {
                continue;
            }
            $service = $line->serviceId !== null ? $catalog->find((int) $line->serviceId) : null;
            $id = $sold->insert([
                'customer_id' => $quote->customerId,
                'service_id' => $line->serviceId,
                'title' => $line->description,
                'stage' => 'attivo',
                'billing_type' => $line->billingType ?: 'una tantum',
                'recurrence' => $line->isRecurring() ? ($line->recurrence ?: 'annuale') : null,
                'price' => round($line->amount() * $factor, 2),
                'cost_price' => $service?->costPrice,
                'supplier_customer_id' => $service?->supplierCustomerId,
                'start_date' => $line->startDate ?: date('Y-m-d'),
                'auto_renew' => $line->isRecurring() ? 1 : 0,
                'notice_days' => 21,
                'payment_plan' => $line->paymentPlan ?: 'unica soluzione',
                'deposit_percent' => $line->depositPercent,
                'installments_count' => $line->installmentsCount,
                'payment_method' => $method,
                'notes' => 'Dal preventivo ' . $quote->quoteNumber,
            ]);
            $plans->createInitialPlan($id);
            $this->lines->update((int) $line->id, ['customer_service_id' => $id]);
            $created++;
        }
        $this->quotes->update($quoteId, ['stage' => 'accettato', 'accepted_at' => date('Y-m-d H:i:s'), 'accepted_via' => mb_substr($via, 0, 60)]);
        if ($customer instanceof Customer) {
            $customers->update((int) $customer->id, array_filter([
                'relationship' => $customer->relationship ?? 'cliente',
                'customer_since' => $customer->customerSince ?? date('Y-m-d'),
            ]));
        }

        return $created;
    }

    /** I preventivi inviati e non accettati entro la validita' diventano "scaduto". */
    public function expireOld(): int
    {
        $count = 0;
        foreach ($this->quotes->findSentExpired(date('Y-m-d')) as $quote) {
            $this->quotes->update((int) $quote->id, ['stage' => 'scaduto']);
            $count++;
        }

        return $count;
    }

    private function assertNoBlockers(Quote $quote): void
    {
        $lines = $this->lines->findByQuote((int) $quote->id);
        if ($lines === []) {
            throw new \InvalidArgumentException('Aggiungi almeno una riga.');
        }
        foreach ($lines as $line) {
            if ((float) $line->unitPrice <= 0) {
                throw new \InvalidArgumentException("Manca il prezzo di \u{ab}{$line->description}\u{bb}.");
            }
        }
    }

    private function userName(int $userId): string
    {
        $user = $this->container->get(UserRepository::class)->find($userId);

        return $user !== null ? trim("{$user->firstName} {$user->lastName}") : 'agenzia';
    }
}

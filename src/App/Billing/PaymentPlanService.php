<?php

declare(strict_types=1);

namespace App\Billing;

use App\Core\Container;
use App\Model\CustomerService;
use App\Package\PackageCatalogService;
use App\Repository\CustomerServiceRepository;
use App\Repository\InstallmentRepository;
use App\Repository\InvoiceRepository;

/**
 * Dal piano di pagamento di un servizio venduto alle righe dello
 * scadenzario: unica soluzione, acconto e saldo, rate mensili. Per un
 * servizio ricorrente il piano vale per ogni periodo: il primo nasce con
 * il servizio, i successivi con renew() (a mano o dal job dei rinnovi
 * automatici).
 *
 * Un canone mensile si paga sempre in un'unica soluzione per mese: rate o
 * acconto su un mese non hanno senso, anche se il piano dice altro.
 */
final class PaymentPlanService
{
    public const PERIOD_MONTHS = ['mensile' => 1, 'trimestrale' => 3, 'semestrale' => 6, 'annuale' => 12];

    private CustomerServiceRepository $services;
    private InstallmentRepository $installments;
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->services = $container->get(CustomerServiceRepository::class);
        $this->installments = $container->get(InstallmentRepository::class);
    }

    public static function periodMonths(?string $recurrence): int
    {
        return self::PERIOD_MONTHS[$recurrence] ?? 12;
    }

    /** Quanto vale il servizio in un anno: solo i ricorrenti, un lavoro una tantum non e' un ricavo che si ripete. */
    public static function annualValue(CustomerService $service): float
    {
        if (!$service->isRecurring() || $service->stage !== 'attivo') {
            return 0.0;
        }

        return (float) $service->price * (12 / self::periodMonths($service->recurrence));
    }

    /**
     * Prime righe dello scadenzario per un servizio appena creato, e la
     * data del primo rinnovo se manca.
     *
     * @return int righe create
     */
    public function createInitialPlan(int $customerServiceId): int
    {
        $service = $this->services->find($customerServiceId);
        if (!$service instanceof CustomerService || $service->startDate === null) {
            return 0;
        }

        if ($service->isRecurring() && $service->renewalDate === null) {
            $renewal = self::addMonths($service->startDate, self::periodMonths($service->recurrence));
            $this->services->update($customerServiceId, ['renewal_date' => $renewal]);
            $service->renewalDate = $renewal;
        }

        return $this->createPeriod($service, $service->startDate, false);
    }

    /**
     * Ricrea le righe da incassare dopo che il piano e' cambiato. Se una
     * parte e' gia' stata incassata non tocca nulla: rifare il piano
     * cancellerebbe la storia di cio' che il cliente ha pagato.
     *
     * @return ?int righe create, null se c'erano gia' incassi
     */
    public function rebuildPlan(int $customerServiceId): ?int
    {
        if ($this->installments->hasPaidForService($customerServiceId)) {
            return null;
        }

        $this->installments->deleteOpenForService($customerServiceId);

        return $this->createInitialPlan($customerServiceId);
    }

    /**
     * Rinnova per un periodo: righe del nuovo periodo e data di rinnovo
     * spostata in avanti. Non fa nulla su un servizio non ricorrente o non
     * attivo.
     *
     * @return int righe create
     */
    public function renew(int $customerServiceId): int
    {
        $service = $this->services->find($customerServiceId);
        if (!$service instanceof CustomerService || !$service->isRecurring() || $service->stage !== 'attivo') {
            return 0;
        }

        $periodStart = $service->renewalDate ?? self::addMonths((string) $service->startDate, self::periodMonths($service->recurrence));
        $created = $this->createPeriod($service, $periodStart, true);

        $this->services->update($customerServiceId, [
            'renewal_date' => self::addMonths($periodStart, self::periodMonths($service->recurrence)),
        ]);

        return $created;
    }

    /**
     * Rinnovi automatici arrivati a scadenza, anche piu' periodi arretrati
     * (es. il job e' rimasto fermo): un canone mensile fermo da tre mesi
     * genera tre mesi.
     *
     * @return array{services: int, installments: int}
     */
    public function runAutoRenewals(string $today): array
    {
        $servicesRenewed = 0;
        $created = 0;
        foreach ($this->services->findDueForAutoRenew($today) as $service) {
            $servicesRenewed++;
            $guard = 0;
            $current = $service;
            while ($current instanceof CustomerService && $current->renewalDate !== null && $current->renewalDate <= $today && $guard++ < 36) {
                $created += $this->renew((int) $current->id);
                $current = $this->services->find((int) $current->id);
            }
        }

        return ['services' => $servicesRenewed, 'installments' => $created];
    }

    public function markPaid(int $installmentId, ?string $paidAt = null, ?string $method = null): void
    {
        $data = ['stage' => 'incassato', 'paid_at' => $paidAt ?? date('Y-m-d')];
        if ($method !== null && $method !== '') {
            $data['payment_method'] = $method;
        }

        $this->installments->update($installmentId, $data);

        // Incassato l'incasso, e' pagata anche la sua fattura (se emessa).
        $installment = $this->installments->find($installmentId);
        if ($installment?->invoiceId !== null && in_array('invoices', PackageCatalogService::installedNames(), true)) {
            $invoices = $this->container->get(InvoiceRepository::class);
            if ($invoices->find((int) $installment->invoiceId)?->stage === 'emessa') {
                $invoices->update((int) $installment->invoiceId, ['stage' => 'pagata']);
            }
        }
    }

    private function createPeriod(CustomerService $service, string $periodStart, bool $isRenewal): int
    {
        $months = self::periodMonths($service->recurrence);
        $periodEnd = $service->isRecurring() ? date('Y-m-d', strtotime(self::addMonths($periodStart, $months) . ' -1 day')) : null;
        $price = round((float) $service->price, 2);
        $plan = $service->isRecurring() && $months === 1 ? 'unica soluzione' : ($service->paymentPlan ?? 'unica soluzione');
        $title = (string) $service->title;
        $periodLabel = $service->isRecurring() ? ' (' . self::periodLabel($periodStart, $months) . ')' : '';

        $rows = match ($plan) {
            'acconto e saldo' => $this->depositAndBalance($service, $periodStart, $price),
            'rate' => $this->monthlyInstallments($service, $periodStart, $price),
            // Il mese di un canone mensile si legge dal periodo: "rinnovo" lo
            // si scrive solo per le scadenze vere (trimestre, anno...).
            default => [[
                'kind' => $isRenewal ? 'rinnovo' : 'unica soluzione',
                'label' => $isRenewal && $months > 1 ? 'rinnovo' : null,
                'due' => $periodStart,
                'amount' => $price,
            ]],
        };

        foreach ($rows as $row) {
            $this->installments->insert([
                'customer_id' => $service->customerId,
                'customer_service_id' => $service->id,
                'kind' => $row['kind'],
                'description' => $title . ($row['label'] !== null ? ' - ' . $row['label'] : '') . $periodLabel,
                'due_date' => $row['due'],
                'amount' => number_format($row['amount'], 2, '.', ''),
                'stage' => 'da incassare',
                'payment_method' => $service->paymentMethod,
                'period_start' => $service->isRecurring() ? $periodStart : null,
                'period_end' => $periodEnd,
            ]);
        }

        return count($rows);
    }

    /** @return list<array{kind:string,label:?string,due:string,amount:float}> */
    private function depositAndBalance(CustomerService $service, string $periodStart, float $price): array
    {
        $percent = $service->depositPercent !== null ? (float) $service->depositPercent : 50.0;
        $deposit = round($price * $percent / 100, 2);

        // Il saldo segue l'acconto della stessa distanza anche nei periodi
        // successivi: la data scelta vale per il primo, poi si sposta.
        $gapDays = 30;
        if ($service->balanceDate !== null && $service->startDate !== null) {
            $gapDays = max(0, (int) ((strtotime($service->balanceDate) - strtotime($service->startDate)) / 86400));
        }

        return [
            ['kind' => 'acconto', 'label' => 'acconto ' . self::percentLabel($percent), 'due' => $periodStart, 'amount' => $deposit],
            ['kind' => 'saldo', 'label' => 'saldo', 'due' => date('Y-m-d', strtotime("{$periodStart} +{$gapDays} days")), 'amount' => round($price - $deposit, 2)],
        ];
    }

    /** @return list<array{kind:string,label:?string,due:string,amount:float}> l'ultima rata assorbe i centesimi dell'arrotondamento */
    private function monthlyInstallments(CustomerService $service, string $periodStart, float $price): array
    {
        $count = max(1, (int) $service->installmentsCount);
        $each = floor($price / $count * 100) / 100;

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $amount = $i === $count - 1 ? round($price - $each * ($count - 1), 2) : $each;
            $rows[] = [
                'kind' => 'rata',
                'label' => 'rata ' . ($i + 1) . ' di ' . $count,
                'due' => self::addMonths($periodStart, $i),
                'amount' => $amount,
            ];
        }

        return $rows;
    }

    /** Aggiunge mesi senza scavalcare il mese: 31 gennaio + 1 mese = 28/29 febbraio, non 3 marzo. */
    public static function addMonths(string $date, int $months): string
    {
        $base = new \DateTimeImmutable($date);
        $day = (int) $base->format('d');
        $firstOfTarget = $base->modify('first day of this month')->modify("+{$months} months");
        $lastDay = (int) $firstOfTarget->format('t');

        return $firstOfTarget->setDate((int) $firstOfTarget->format('Y'), (int) $firstOfTarget->format('m'), min($day, $lastDay))->format('Y-m-d');
    }

    private static function periodLabel(string $periodStart, int $months): string
    {
        $mesi = ['', 'gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
        $start = new \DateTimeImmutable($periodStart);
        if ($months === 1) {
            return $mesi[(int) $start->format('n')] . ' ' . $start->format('Y');
        }
        $end = new \DateTimeImmutable(date('Y-m-d', strtotime(self::addMonths($periodStart, $months) . ' -1 day')));

        return $mesi[(int) $start->format('n')] . ' ' . $start->format('Y') . ' - ' . $mesi[(int) $end->format('n')] . ' ' . $end->format('Y');
    }

    private static function percentLabel(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, ',', ''), '0'), ',') . '%';
    }
}

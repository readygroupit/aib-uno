<?php

declare(strict_types=1);

namespace App\Billing;

use App\Core\Container;
use App\Model\Customer;
use App\Model\CustomerContact;
use App\Model\CustomerService;
use App\Model\CustomerTeamMember;
use App\Model\Installment;
use App\Package\PackageCatalogService;
use App\Payments\PaymentService;
use App\Repository\CustomerContactRepository;
use App\Repository\CustomerRepository;
use App\Repository\CustomerServiceRepository;
use App\Repository\CustomerTeamMemberRepository;
use App\Repository\InstallmentRepository;
use App\Repository\UserRepository;
use App\Support\ItalianFormat as F;

/**
 * La situazione dei clienti in una frase e pochi numeri: cosa hanno
 * attivo, quanto valgono in un anno, cosa scade, cosa devono ancora
 * pagare e cosa conviene fare adesso. Lo usano la lista clienti, la
 * scheda cliente e lo scadenzario, cosi' i tre dicono le stesse cose.
 *
 * Funziona anche con il solo pacchetto 'customers': senza servizi o
 * scadenzario installati quelle parti restano vuote.
 */
final class AccountOverviewService
{
    /** Un rinnovo entro questi giorni compare tra quelli "vicini". */
    public const RENEWAL_HORIZON_DAYS = 60;

    private const RELATIONSHIP_LABELS = [
        'cliente' => 'Cliente',
        'intermediario' => 'Intermediario',
        'fornitore' => 'Fornitore',
        'collaboratore' => 'Collaboratore',
        'influencer' => 'Influencer',
    ];

    private CustomerRepository $customers;
    private CustomerContactRepository $contacts;
    private ?CustomerServiceRepository $services = null;
    private ?InstallmentRepository $installments = null;
    private UserRepository $users;
    private string $today;
    private Container $container;
    private ?bool $onlinePayments = null;
    private bool $hasInvoices = false;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->customers = $container->get(CustomerRepository::class);
        $this->contacts = $container->get(CustomerContactRepository::class);
        $this->users = $container->get(UserRepository::class);
        $installed = PackageCatalogService::installedNames();
        if (in_array('customer_services', $installed, true)) {
            $this->services = $container->get(CustomerServiceRepository::class);
        }
        if (in_array('installments', $installed, true)) {
            $this->installments = $container->get(InstallmentRepository::class);
        }
        $this->hasInvoices = in_array('invoices', $installed, true);
        $this->today = date('Y-m-d');
    }

    public function today(): string
    {
        return $this->today;
    }

    public function hasBilling(): bool
    {
        return $this->services !== null && $this->installments !== null;
    }

    /**
     * Una riga per cliente con tutto quello che serve alla lista e al
     * pannello laterale.
     *
     * @return list<array>
     */
    public function customerRows(): array
    {
        $customers = $this->customers->findAll([], "FIELD(relationship, 'cliente', 'intermediario', 'fornitore', 'collaboratore'), FIELD(importance, 'chiave', 'abituale', 'occasionale'), company_name, last_name");
        $servicesByCustomer = $this->servicesByCustomer();
        $open = $this->installments?->openSummaryByCustomer($this->today) ?? [];
        $openInstallments = $this->openInstallmentsByCustomer();
        $userNames = $this->userNames();
        $referrals = $this->referralCounts($customers);
        $names = [];
        foreach ($customers as $customer) {
            $names[(int) $customer->id] = $customer->displayName();
        }

        $rows = [];
        foreach ($customers as $customer) {
            $id = (int) $customer->id;
            $rows[] = $this->buildRow(
                $customer,
                $servicesByCustomer[$id] ?? [],
                $open[$id] ?? null,
                $openInstallments[$id] ?? [],
                $this->contacts->findByCustomer($id),
                $userNames,
                $referrals[$id] ?? 0,
                $names
            );
        }

        return $rows;
    }

    /** Numeri di testa della lista clienti e del cruscotto. */
    public function totals(array $rows): array
    {
        $annual = 0.0;
        $activeServices = 0;
        $renewalsSoon = 0;
        $renewalsSoonValue = 0.0;
        $overdue = 0.0;
        $overdueCustomers = 0;
        $open = 0.0;
        $clients = 0;
        foreach ($rows as $row) {
            $annual += $row['annualAmount'];
            $activeServices += $row['activeServicesCount'];
            $renewalsSoon += $row['renewalsSoonCount'];
            $renewalsSoonValue += $row['renewalsSoonValue'];
            $overdue += $row['overdueAmount'];
            $open += $row['openAmount'];
            if ($row['overdueAmount'] > 0) {
                $overdueCustomers++;
            }
            if ($row['relationshipKey'] === 'cliente') {
                $clients++;
            }
        }

        return compact('annual', 'activeServices', 'renewalsSoon', 'renewalsSoonValue', 'overdue', 'overdueCustomers', 'open', 'clients');
    }

    /** Tutto per la scheda di un cliente. */
    public function customerProfile(Customer $customer): array
    {
        $id = (int) $customer->id;
        $services = $this->services?->findByCustomer($id) ?? [];
        $installments = $this->installments?->findByCustomer($id) ?? [];
        $contacts = $this->contacts->findByCustomer($id);
        $userNames = $this->userNames();
        $open = $this->installments?->openSummaryByCustomer($this->today)[$id] ?? null;
        $openInstallments = array_values(array_filter($installments, static fn (Installment $i) => $i->stage === 'da incassare'));
        $row = $this->buildRow($customer, $services, $open, $openInstallments, $contacts, $userNames, 0, []);

        $referrer = null;
        if ($customer->referrerCustomerId !== null) {
            $referrerModel = $this->customers->find((int) $customer->referrerCustomerId);
            if ($referrerModel instanceof Customer) {
                $referrer = ['name' => $referrerModel->displayName(), 'href' => '/clienti/' . $referrerModel->id];
            }
        }

        $brought = array_map(
            static fn (Customer $c) => ['name' => $c->displayName(), 'href' => '/clienti/' . $c->id],
            $this->customers->findAll(['referrer_customer_id' => $id], 'company_name')
        );

        $serviceTitles = [];
        foreach ($services as $service) {
            $serviceTitles[(int) $service->id] = (string) $service->title;
        }

        return [
            'row' => $row,
            'referrer' => $referrer,
            'brought' => $brought,
            'services' => array_map(fn (CustomerService $s) => $this->serviceCard($s), $services),
            'installments' => $this->installmentTimeline($installments, $serviceTitles),
            'contacts' => array_map(fn (CustomerContact $c) => $this->contactData($c), $contacts),
            'team' => $this->teamData($id, $userNames),
        ];
    }

    /** La squadra interna del cliente: chi fa cosa. */
    private function teamData(int $customerId, array $userNames): array
    {
        return array_map(static fn (CustomerTeamMember $m) => [
            'name' => $userNames[(int) $m->userId] ?? '',
            'role' => (string) $m->role,
            'notes' => $m->notes,
            'href' => '/clienti/squadra/' . $m->id,
        ], $this->container->get(CustomerTeamMemberRepository::class)->findByCustomer($customerId));
    }

    /**
     * I clienti di una persona: quelli di cui e' referente interno o in
     * squadra. Per il filtro "I miei clienti".
     *
     * @return int[]
     */
    public function customerIdsFor(int $userId): array
    {
        $ids = $this->container->get(CustomerTeamMemberRepository::class)->customerIdsForUser($userId);
        foreach ($this->customers->findAll(['account_user_id' => $userId]) as $customer) {
            $ids[] = (int) $customer->id;
        }

        return array_values(array_unique($ids));
    }

    /** Testata e scadenze di un singolo servizio venduto. */
    public function serviceDetail(CustomerService $service): array
    {
        $installments = $this->installments?->findByService((int) $service->id) ?? [];

        return [
            'service' => $this->serviceCard($service),
            'installments' => array_map(
                fn (Installment $i) => $this->installmentItem($i, [], [(int) $service->id => (string) $service->title]),
                $installments
            ),
        ];
    }

    /**
     * Lo scadenzario: incassi aperti per gruppo di urgenza, incassati di
     * recente e rinnovi vicini.
     */
    public function dueBoard(): array
    {
        if (!$this->hasBilling()) {
            return ['groups' => [], 'renewals' => [], 'totals' => [], 'paidPerMonth' => [], 'recentPaid' => []];
        }

        $names = [];
        foreach ($this->customers->findAll() as $customer) {
            $names[(int) $customer->id] = $customer->displayName();
        }

        $horizon = date('Y-m-d', strtotime($this->today . ' +90 days'));
        $open = $this->installments->findOpenUntil($horizon);
        $titles = $this->services->titlesByIds(array_map(static fn (Installment $i) => $i->customerServiceId, $open));

        $weekEnd = date('Y-m-d', strtotime($this->today . ' +7 days'));
        $monthEnd = date('Y-m-d', strtotime($this->today . ' +30 days'));
        $groups = [
            'overdue' => ['key' => 'overdue', 'title' => 'Scaduti', 'tone' => 'danger', 'items' => [], 'total' => 0.0],
            'week' => ['key' => 'week', 'title' => 'Questa settimana', 'tone' => 'warning', 'items' => [], 'total' => 0.0],
            'month' => ['key' => 'month', 'title' => 'Entro 30 giorni', 'tone' => 'neutral', 'items' => [], 'total' => 0.0],
            'later' => ['key' => 'later', 'title' => 'Entro 90 giorni', 'tone' => 'neutral', 'items' => [], 'total' => 0.0],
        ];
        foreach ($open as $installment) {
            $due = (string) $installment->dueDate;
            $group = match (true) {
                $due < $this->today => 'overdue',
                $due <= $weekEnd => 'week',
                $due <= $monthEnd => 'month',
                default => 'later',
            };
            $groups[$group]['items'][] = $this->installmentItem($installment, $names, $titles);
            $groups[$group]['total'] += (float) $installment->amount;
        }
        foreach ($groups as &$group) {
            $group['totalLabel'] = F::euro($group['total']);
        }
        unset($group);

        $monthStart = date('Y-m-01', strtotime($this->today));
        $paidThisMonth = $this->installments->findPaidBetween($monthStart, $this->today);
        $recentPaid = array_slice($this->installments->findPaidBetween(date('Y-m-d', strtotime($this->today . ' -30 days')), $this->today), 0, 6);

        $renewals = [];
        $renewalsValue = 0.0;
        foreach ($this->services->findRenewalsUntil(date('Y-m-d', strtotime($this->today . ' +' . self::RENEWAL_HORIZON_DAYS . ' days'))) as $service) {
            // Un canone mensile automatico si rinnova ogni mese da solo:
            // non e' una scadenza su cui decidere qualcosa.
            if ($service->recurrence === 'mensile' && (int) $service->autoRenew === 1) {
                continue;
            }
            $renewals[] = $this->renewalItem($service, $names);
            $renewalsValue += (float) $service->price;
        }

        return [
            'groups' => array_values($groups),
            'renewals' => $renewals,
            'totals' => [
                'overdue' => $groups['overdue']['total'],
                'overdueCount' => count($groups['overdue']['items']),
                'next30' => $groups['week']['total'] + $groups['month']['total'],
                'next30Count' => count($groups['week']['items']) + count($groups['month']['items']),
                'paidThisMonth' => array_sum(array_map(static fn (Installment $i) => (float) $i->amount, $paidThisMonth)),
                'paidThisMonthCount' => count($paidThisMonth),
                'renewalsCount' => count($renewals),
                'renewalsValue' => $renewalsValue,
            ],
            'paidPerMonth' => $this->installments->paidPerMonth(6, $this->today),
            'recentPaid' => array_map(fn (Installment $i) => $this->installmentItem($i, $names, $titles), $recentPaid),
        ];
    }

    /**
     * @param CustomerService[] $services
     * @param Installment[] $openInstallments
     * @param CustomerContact[] $contacts
     */
    private function buildRow(Customer $customer, array $services, ?array $open, array $openInstallments, array $contacts, array $userNames, int $referralCount, array $names): array
    {
        $id = (int) $customer->id;
        $name = $customer->displayName();
        $active = array_values(array_filter($services, static fn (CustomerService $s) => $s->stage === 'attivo'));

        $annual = 0.0;
        $oneOff = 0.0;
        foreach ($active as $service) {
            $annual += PaymentPlanService::annualValue($service);
            if (!$service->isRecurring()) {
                $oneOff += (float) $service->price;
            }
        }

        $nextRenewal = $this->nextRenewal($active);
        $renewalsSoon = array_filter($active, fn (CustomerService $s) => $s->isRecurring() && $s->renewalDate !== null
            && F::daysFrom($s->renewalDate, $this->today) <= self::RENEWAL_HORIZON_DAYS
            && !($s->recurrence === 'mensile' && (int) $s->autoRenew === 1));

        $overdue = (float) ($open['overdue'] ?? 0);
        $openAmount = (float) ($open['open'] ?? 0);
        $decisionMaker = $contacts[0] ?? null;

        $relationshipKey = $customer->relationship ?? 'cliente';
        $buckets = ['tutti'];
        if ($customer->importance === 'chiave') {
            $buckets[] = 'chiave';
        }
        if ($renewalsSoon !== []) {
            $buckets[] = 'rinnovi';
        }
        if ($overdue > 0) {
            $buckets[] = 'scaduti';
        }
        if ($relationshipKey === 'intermediario') {
            $buckets[] = 'intermediari';
        }
        if (in_array($relationshipKey, ['fornitore', 'collaboratore', 'influencer'], true)) {
            $buckets[] = 'fornitori';
        }

        $subtitleParts = array_filter([
            $customer->sector,
            $customer->accountUserId !== null && isset($userNames[(int) $customer->accountUserId]) ? 'lo segue ' . $userNames[(int) $customer->accountUserId] : null,
        ]);

        return [
            'id' => $id,
            'href' => '/clienti/' . $id,
            'name' => $name,
            'initials' => F::initials($name),
            'subtitle' => implode(' · ', $subtitleParts) ?: ($customer->email ?? ''),
            'searchText' => mb_strtolower(implode(' ', array_filter([$name, $customer->firstName, $customer->lastName, $customer->email, $customer->sector, $customer->vatNumber]))),
            'relationshipKey' => $relationshipKey,
            'relationship' => self::RELATIONSHIP_LABELS[$relationshipKey] ?? ucfirst($relationshipKey),
            'isKey' => $customer->importance === 'chiave',
            'importance' => $customer->importance,
            'services' => array_map(static fn (CustomerService $s) => ['title' => (string) $s->title, 'recurring' => $s->isRecurring()], array_slice($active, 0, 3)),
            'servicesMore' => max(0, count($active) - 3),
            'activeServicesCount' => count($active),
            'annualAmount' => $annual,
            'annualValue' => $annual > 0 ? F::euro($annual) : null,
            'annualCaption' => $annual > 0 ? ($oneOff > 0 ? '+' . F::euro($oneOff) . ' extra' : "all'anno") : ($oneOff > 0 ? F::euro($oneOff) . ' una tantum' : null),
            'nextRenewal' => $nextRenewal,
            'renewalsSoonCount' => count($renewalsSoon),
            'renewalsSoonValue' => array_sum(array_map(static fn (CustomerService $s) => (float) $s->price, $renewalsSoon)),
            'overdueAmount' => $overdue,
            'openAmount' => $openAmount,
            'payment' => $this->paymentState($open),
            'status' => $this->status($open, $nextRenewal, $openInstallments, count($active), $relationshipKey),
            'buckets' => $buckets,
            'referralCount' => $referralCount,
            'detail' => [
                'kpis' => array_values(array_filter([
                    ['label' => 'Valore annuo', 'value' => $annual > 0 ? F::euro($annual) : '—', 'tone' => 'strong'],
                    ['label' => 'Da incassare', 'value' => $openAmount > 0 ? F::euro($openAmount) : '—', 'tone' => 'neutral'],
                    $overdue > 0 ? ['label' => 'Scaduto', 'value' => F::euro($overdue), 'tone' => 'danger'] : null,
                ])),
                'services' => array_map(fn (CustomerService $s) => $this->serviceLine($s), $active),
                'installments' => array_map(fn (Installment $i) => [
                    'date' => F::shortDate($i->dueDate, $this->today),
                    'label' => (string) $i->description,
                    'amount' => F::euro((float) $i->amount),
                    'tone' => (string) $i->dueDate < $this->today ? 'danger' : 'neutral',
                ], array_slice($openInstallments, 0, 3)),
                'contacts' => array_map(fn (CustomerContact $c) => $this->contactData($c), array_slice($contacts, 0, 2)),
                'referralCount' => $referralCount,
                'suggestion' => $this->suggestion($customer, $active, $openInstallments, $decisionMaker),
                'newServiceHref' => $this->services !== null ? '/servizi-clienti/nuovo?customer_id=' . $id : null,
                'email' => $customer->email,
                'phone' => $customer->mobilePhone ?: $customer->phone,
            ],
        ];
    }

    /** @param CustomerService[] $active */
    private function nextRenewal(array $active): ?array
    {
        $candidates = array_filter($active, static fn (CustomerService $s) => $s->isRecurring() && $s->renewalDate !== null);
        if ($candidates === []) {
            return null;
        }
        // I canoni mensili rinnovano ogni mese: si mostra prima una
        // scadenza "vera" (trimestrale, annuale...), se c'e'.
        $longer = array_filter($candidates, static fn (CustomerService $s) => $s->recurrence !== 'mensile');
        $pool = $longer !== [] ? $longer : $candidates;
        usort($pool, static fn (CustomerService $a, CustomerService $b) => strcmp((string) $a->renewalDate, (string) $b->renewalDate));
        $service = $pool[0];

        return [
            'title' => (string) $service->title,
            'date' => F::shortDate($service->renewalDate, $this->today),
            'when' => F::relativeDays((string) $service->renewalDate, $this->today),
            'tone' => $this->renewalTone($service),
            'auto' => (int) $service->autoRenew === 1,
            'monthly' => $service->recurrence === 'mensile',
        ];
    }

    private function renewalTone(CustomerService $service): string
    {
        $days = F::daysFrom((string) $service->renewalDate, $this->today);
        // Un canone mensile automatico non e' una scadenza da presidiare.
        if ($service->recurrence === 'mensile' && (int) $service->autoRenew === 1) {
            return $days < 0 ? 'danger' : 'neutral';
        }
        $notice = (int) ($service->noticeDays ?? 21);

        return match (true) {
            $days < 0 => 'danger',
            $days <= $notice => 'warning',
            default => 'neutral',
        };
    }

    /**
     * La cosa piu' urgente del cliente in una cella: soldi scaduti, poi un
     * rinnovo entro il preavviso, poi un pagamento in settimana, poi il
     * prossimo rinnovo; altrimenti "in regola".
     *
     * @param Installment[] $openInstallments
     */
    private function status(?array $open, ?array $nextRenewal, array $openInstallments, int $activeCount, string $relationship): array
    {
        if ($open !== null && $open['overdue'] > 0) {
            $days = $open['oldestOverdue'] !== null ? -F::daysFrom($open['oldestOverdue'], $this->today) : 0;

            return ['label' => F::euro($open['overdue']) . ' scaduti', 'caption' => 'da ' . F::plural($days, 'giorno', 'giorni') . ' · da sollecitare', 'tone' => 'danger', 'icon' => 'circle-exclamation'];
        }
        if ($nextRenewal !== null && in_array($nextRenewal['tone'], ['warning', 'danger'], true)) {
            return [
                'label' => 'Rinnovo ' . $nextRenewal['date'],
                'caption' => $nextRenewal['title'] . ' · ' . ($nextRenewal['auto'] ? 'automatico' : 'da confermare'),
                'tone' => 'warning',
                'icon' => $nextRenewal['auto'] ? 'arrows-rotate' : 'calendar',
            ];
        }
        $soon = array_values(array_filter($openInstallments, fn (Installment $i) => F::daysFrom((string) $i->dueDate, $this->today) <= 7));
        if ($soon !== []) {
            $total = array_sum(array_map(static fn (Installment $i) => (float) $i->amount, $soon));

            return ['label' => F::euro($total) . ' in arrivo', 'caption' => F::relativeDays((string) $soon[0]->dueDate, $this->today) . ' · ' . $soon[0]->description, 'tone' => 'neutral', 'icon' => 'coins'];
        }
        if ($nextRenewal !== null && !$nextRenewal['monthly']) {
            return ['label' => 'Rinnovo ' . $nextRenewal['date'], 'caption' => $nextRenewal['when'] . ' · ' . $nextRenewal['title'], 'tone' => 'neutral', 'icon' => $nextRenewal['auto'] ? 'arrows-rotate' : 'calendar'];
        }
        if ($activeCount === 0 && $relationship === 'cliente' && $this->services !== null) {
            return ['label' => 'Nessun servizio', 'caption' => 'da ricontattare', 'tone' => 'neutral', 'icon' => null];
        }

        return ['label' => 'In regola', 'caption' => $open !== null && $open['open'] > 0 ? F::euro($open['open']) . ' da incassare piu\' avanti' : null, 'tone' => 'success', 'icon' => 'circle-check'];
    }

    private function paymentState(?array $open): array
    {
        if ($this->installments === null) {
            return ['label' => '—', 'caption' => null, 'tone' => 'neutral'];
        }
        if ($open === null || $open['open'] <= 0) {
            return ['label' => 'In regola', 'caption' => null, 'tone' => 'success'];
        }
        if ($open['overdue'] > 0) {
            $days = $open['oldestOverdue'] !== null ? -F::daysFrom($open['oldestOverdue'], $this->today) : 0;

            return ['label' => F::euro($open['overdue']) . ' scaduti', 'caption' => 'da ' . F::plural($days, 'giorno', 'giorni'), 'tone' => 'danger'];
        }

        return [
            'label' => F::euro($open['open']) . ' da incassare',
            'caption' => $open['nextDue'] !== null ? 'prossimo ' . F::shortDate($open['nextDue'], $this->today) : null,
            'tone' => 'neutral',
        ];
    }

    /**
     * La cosa da fare adesso per questo cliente, in una frase - in ordine
     * di urgenza: soldi scaduti, rinnovo da confermare, cliente senza
     * servizi.
     *
     * @param CustomerService[] $active
     * @param Installment[] $openInstallments
     */
    private function suggestion(Customer $customer, array $active, array $openInstallments, ?CustomerContact $decisionMaker): ?array
    {
        $who = $decisionMaker?->fullName ?? 'il cliente';

        $overdue = array_values(array_filter($openInstallments, fn (Installment $i) => (string) $i->dueDate < $this->today));
        if ($overdue !== []) {
            $total = array_sum(array_map(static fn (Installment $i) => (float) $i->amount, $overdue));
            $days = -F::daysFrom((string) $overdue[0]->dueDate, $this->today);
            $text = count($overdue) === 1
                ? 'Sollecita ' . F::euro($total) . ' scaduti da ' . F::plural($days, 'giorno', 'giorni') . ': ' . $overdue[0]->description . '.'
                : 'Sollecita ' . F::euro($total) . ' scaduti: ' . count($overdue) . ' pagamenti, il piu\' vecchio da ' . F::plural($days, 'giorno', 'giorni') . '.';

            return ['tone' => 'danger', 'title' => 'Pagamento in ritardo', 'text' => $text];
        }

        foreach ($active as $service) {
            if (!$service->isRecurring() || $service->renewalDate === null || $service->recurrence === 'mensile') {
                continue;
            }
            $days = F::daysFrom($service->renewalDate, $this->today);
            if ($days > (int) ($service->noticeDays ?? 21)) {
                continue;
            }
            $date = F::shortDate($service->renewalDate, $this->today);
            if ((int) $service->autoRenew === 1) {
                return ['tone' => 'warning', 'title' => 'Rinnovo automatico in arrivo', 'text' => "\u{ab}{$service->title}\u{bb} si rinnova da solo il {$date}: avvisa {$who} che arrivera' l'addebito di " . F::euro((float) $service->price) . '.'];
            }

            return ['tone' => 'warning', 'title' => 'Rinnovo da confermare', 'text' => "\u{ab}{$service->title}\u{bb} scade il {$date} (" . F::relativeDays($service->renewalDate, $this->today) . "): senti {$who} per confermare il rinnovo."];
        }

        if ($active === [] && ($customer->relationship ?? 'cliente') === 'cliente' && $this->services !== null) {
            return ['tone' => 'info', 'title' => 'Nessun servizio attivo', 'text' => 'Questo cliente oggi non ha servizi attivi: e\' un buon momento per proporgli qualcosa.'];
        }

        return null;
    }

    private function serviceLine(CustomerService $service): array
    {
        return [
            'title' => (string) $service->title,
            'meta' => $this->priceLabel($service),
            'renewal' => $service->isRecurring() && $service->renewalDate !== null
                ? ['label' => 'rinnovo ' . F::shortDate($service->renewalDate, $this->today), 'tone' => $this->renewalTone($service), 'auto' => (int) $service->autoRenew === 1]
                : null,
            'href' => '/servizi-clienti/' . $service->id,
        ];
    }

    private function serviceCard(CustomerService $service): array
    {
        $price = (float) $service->price;
        $cost = $service->costPrice !== null ? (float) $service->costPrice : null;
        $margin = $cost !== null && $price > 0 ? (int) round(($price - $cost) / $price * 100) : null;

        return [
            'id' => (int) $service->id,
            'href' => '/servizi-clienti/' . $service->id,
            'title' => (string) $service->title,
            'stage' => (string) $service->stage,
            'stageTone' => match ($service->stage) {
                'attivo' => 'success',
                'sospeso' => 'warning',
                'disdetto' => 'danger',
                default => 'neutral',
            },
            'kind' => $service->isRecurring() ? 'Ricorrente ' . $service->recurrence : 'Una tantum',
            'price' => $this->priceLabel($service),
            'plan' => $this->planLabel($service),
            'since' => $service->startDate !== null ? 'dal ' . F::shortDate($service->startDate, $this->today) : null,
            'renewal' => $service->stage === 'attivo' && $service->isRecurring() && $service->renewalDate !== null ? [
                'date' => F::shortDate($service->renewalDate, $this->today),
                'when' => F::relativeDays($service->renewalDate, $this->today),
                'tone' => $this->renewalTone($service),
                'auto' => (int) $service->autoRenew === 1,
            ] : null,
            'margin' => $margin !== null ? "margine {$margin}%" : null,
            'canRenew' => $service->stage === 'attivo' && $service->isRecurring(),
        ];
    }

    public function priceLabel(CustomerService $service): string
    {
        $suffix = match ($service->recurrence) {
            'mensile' => ' al mese',
            'trimestrale' => ' a trimestre',
            'semestrale' => ' a semestre',
            'annuale' => " all'anno",
            default => '',
        };

        return F::euro((float) $service->price) . ($service->isRecurring() ? $suffix : '');
    }

    private function planLabel(CustomerService $service): string
    {
        $method = $service->paymentMethod !== null ? ' · ' . $service->paymentMethod : '';
        if ($service->isRecurring() && $service->recurrence === 'mensile') {
            return 'ogni mese' . $method;
        }

        return match ($service->paymentPlan) {
            'acconto e saldo' => 'acconto ' . rtrim(rtrim(number_format((float) ($service->depositPercent ?? 50), 2, ',', ''), '0'), ',') . '% e saldo' . $method,
            'rate' => F::plural((int) ($service->installmentsCount ?? 1), 'rata', 'rate') . ' mensili' . $method,
            default => 'unica soluzione' . $method,
        };
    }

    /**
     * Prima quello che si aspetta (dal piu' vicino), poi gli ultimi
     * incassati: la storia completa resta nello scadenzario.
     *
     * @param Installment[] $installments
     */
    private function installmentTimeline(array $installments, array $serviceTitles): array
    {
        $open = array_values(array_filter($installments, static fn (Installment $i) => $i->stage === 'da incassare'));
        $paid = array_values(array_filter($installments, static fn (Installment $i) => $i->stage === 'incassato'));
        usort($paid, static fn (Installment $a, Installment $b) => strcmp((string) $b->paidAt, (string) $a->paidAt));

        $items = [];
        foreach (array_slice($open, 0, 8) as $installment) {
            $items[] = $this->installmentItem($installment, [], $serviceTitles);
        }
        foreach (array_slice($paid, 0, 4) as $installment) {
            $items[] = $this->installmentItem($installment, [], $serviceTitles);
        }

        return $items;
    }

    private function installmentItem(Installment $installment, array $customerNames, array $serviceTitles): array
    {
        $isPaid = $installment->stage === 'incassato';
        $due = (string) $installment->dueDate;
        $days = F::daysFrom($due, $this->today);

        return [
            'id' => (int) $installment->id,
            'href' => '/scadenzario/' . $installment->id,
            'customer' => $customerNames[(int) $installment->customerId] ?? null,
            'customerHref' => '/clienti/' . $installment->customerId,
            'description' => (string) $installment->description,
            'kind' => (string) $installment->kind,
            'amount' => F::euro((float) $installment->amount),
            'date' => F::shortDate($isPaid ? $installment->paidAt : $due, $this->today),
            'when' => $isPaid ? 'incassato' : ($days < 0 ? 'scaduto da ' . F::plural(-$days, 'giorno', 'giorni') : F::relativeDays($due, $this->today)),
            'tone' => $isPaid ? 'success' : ($days < 0 ? 'danger' : ($days <= 7 ? 'warning' : 'neutral')),
            'paid' => $isPaid,
            'method' => $installment->paymentMethod,
            'reminded' => $installment->reminderSentAt !== null ? 'sollecitato il ' . F::shortDate(substr($installment->reminderSentAt, 0, 10), $this->today) : null,
            'payAction' => $isPaid ? null : '/scadenzario/' . $installment->id . '/incassa',
            'linkAction' => !$isPaid && $this->onlinePayments() ? '/scadenzario/' . $installment->id . '/link-pagamento' : null,
            'invoiceHref' => $this->hasInvoices && $installment->invoiceId !== null ? '/fatture/' . $installment->invoiceId : null,
            'invoiceAction' => $this->hasInvoices && $installment->invoiceId === null && $installment->stage !== 'annullato' ? '/scadenzario/' . $installment->id . '/fattura' : null,
        ];
    }

    /** Stripe o PayPal collegati: allora ogni incasso aperto ha il suo link di pagamento. */
    private function onlinePayments(): bool
    {
        return $this->onlinePayments ??= $this->container->get(PaymentService::class)->hasOnlinePayments();
    }

    private function renewalItem(CustomerService $service, array $customerNames): array
    {
        $days = F::daysFrom((string) $service->renewalDate, $this->today);
        $notice = (int) ($service->noticeDays ?? 21);
        $isAuto = (int) $service->autoRenew === 1;

        $status = match (true) {
            $days < 0 && $isAuto => 'rinnovo automatico in attesa',
            $days < 0 => 'scaduto: da rinnovare o chiudere',
            $days <= $notice && $isAuto => 'avvisa il cliente dell\'addebito',
            $days <= $notice => 'da confermare con il cliente',
            $isAuto => 'si rinnova da solo',
            default => 'da confermare',
        };

        return [
            'id' => (int) $service->id,
            'href' => '/servizi-clienti/' . $service->id,
            'customer' => $customerNames[(int) $service->customerId] ?? '',
            'customerHref' => '/clienti/' . $service->customerId,
            'title' => (string) $service->title,
            'amount' => $this->priceLabel($service),
            'date' => F::shortDate($service->renewalDate, $this->today),
            'when' => F::relativeDays((string) $service->renewalDate, $this->today),
            'tone' => $this->renewalTone($service),
            'auto' => $isAuto,
            'status' => $status,
            'renewAction' => '/servizi-clienti/' . $service->id . '/rinnova',
        ];
    }

    private function contactData(CustomerContact $contact): array
    {
        return [
            'name' => (string) $contact->fullName,
            'role' => $contact->role,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'decides' => (int) $contact->isDecisionMaker === 1,
            'href' => '/clienti/referenti/' . $contact->id,
        ];
    }

    /** @return array<int, CustomerService[]> */
    private function servicesByCustomer(): array
    {
        if ($this->services === null) {
            return [];
        }
        $grouped = [];
        foreach ($this->services->findAll([], 'start_date') as $service) {
            $grouped[(int) $service->customerId][] = $service;
        }

        return $grouped;
    }

    /** @return array<int, Installment[]> */
    private function openInstallmentsByCustomer(): array
    {
        if ($this->installments === null) {
            return [];
        }
        $grouped = [];
        foreach ($this->installments->findOpenUntil(date('Y-m-d', strtotime($this->today . ' +365 days'))) as $installment) {
            $grouped[(int) $installment->customerId][] = $installment;
        }

        return $grouped;
    }

    /** @return array<int,string> id utente => nome */
    private function userNames(): array
    {
        $names = [];
        foreach ($this->users->findAll() as $user) {
            $names[(int) $user->id] = trim((string) $user->firstName) ?: (string) $user->username;
        }

        return $names;
    }

    /** @param Customer[] $customers @return array<int,int> intermediario => clienti portati */
    private function referralCounts(array $customers): array
    {
        $counts = [];
        foreach ($customers as $customer) {
            if ($customer->referrerCustomerId !== null) {
                $counts[(int) $customer->referrerCustomerId] = ($counts[(int) $customer->referrerCustomerId] ?? 0) + 1;
            }
        }

        return $counts;
    }
}

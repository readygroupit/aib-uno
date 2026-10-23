<?php

declare(strict_types=1);

namespace App\ClientRequests;

use App\Core\Container;
use App\Model\ClientRequest;
use App\Repository\ClientRequestRepository;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use App\Support\ItalianFormat as F;

/** Le richieste dei clienti da mostrare: da smistare, in corso, extra da mettere in conto, fatte. */
final class ClientRequestOverviewService
{
    private Container $container;
    private string $today;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->today = date('Y-m-d');
    }

    public function board(?int $meId): array
    {
        $requests = $this->container->get(ClientRequestRepository::class)->findForBoard(date('Y-m-d', strtotime($this->today . ' -7 days')));
        $names = $this->customerNames();
        $people = $this->people();
        $item = fn (ClientRequest $r) => $this->item($r, $names, $people);

        $toAssign = array_filter($requests, static fn (ClientRequest $r) => $r->isOpen() && $r->assignedUserId === null);
        $inProgress = array_filter($requests, static fn (ClientRequest $r) => $r->isOpen() && $r->assignedUserId !== null);
        $toBill = array_filter($requests, static fn (ClientRequest $r) => $r->stage === 'fatta' && (int) $r->isExtra === 1 && $r->installmentId === null);
        $done = array_filter($requests, fn (ClientRequest $r) => $r->stage === 'fatta' && $r->doneAt !== null && substr($r->doneAt, 0, 10) >= date('Y-m-d', strtotime($this->today . ' -7 days')) && !((int) $r->isExtra === 1 && $r->installmentId === null));
        $mine = array_filter($inProgress, static fn (ClientRequest $r) => $r->assignedUserId === $meId);
        $late = array_filter($inProgress, fn (ClientRequest $r) => $r->dueDate !== null && $r->dueDate < $this->today);

        $counts = $this->container->get(ClientRequestRepository::class)->countPerCustomerSince(date('Y-m-d', strtotime($this->today . ' -30 days')));
        arsort($counts);
        $top = array_slice(array_map(static fn ($id, $n) => ['name' => $names[$id] ?? '', 'count' => $n, 'href' => '/clienti/' . $id], array_keys($counts), $counts), 0, 5);

        return [
            'totals' => [
                'toAssign' => count($toAssign),
                'inProgress' => count($inProgress),
                'mine' => count($mine),
                'late' => count($late),
                'toBill' => count($toBill),
                'toBillAmount' => array_sum(array_map(static fn (ClientRequest $r) => (float) $r->extraAmount, $toBill)),
                'lastMonth' => array_sum($counts),
            ],
            'groups' => array_values(array_filter([
                ['key' => 'assign', 'title' => 'Da smistare', 'tone' => 'warning', 'items' => array_values(array_map($item, $toAssign))],
                ['key' => 'progress', 'title' => 'In corso', 'tone' => count($late) > 0 ? 'danger' : 'info', 'items' => array_values(array_map($item, $inProgress))],
                ['key' => 'bill', 'title' => 'Extra da mettere in conto', 'tone' => 'warning', 'items' => array_values(array_map($item, $toBill))],
            ], static fn (array $g) => $g['items'] !== [])),
            'done' => array_values(array_map($item, $done)),
            'top' => $top,
        ];
    }

    private function item(ClientRequest $r, array $names, array $people): array
    {
        $base = '/richieste/' . $r->id;
        $late = $r->isOpen() && $r->dueDate !== null && $r->dueDate < $this->today;
        $actions = [];
        if ($r->isOpen() && $r->assignedUserId === null) {
            $actions[] = ['label' => 'Prendo io', 'action' => "{$base}/prendo", 'variant' => 'secondary', 'icon' => 'user'];
        }
        if ($r->isOpen()) {
            $actions[] = ['label' => 'Fatta', 'action' => "{$base}/fatta", 'variant' => 'secondary', 'icon' => 'check', 'ask' => "Com'e' finita? (facoltativo)", 'placeholder' => 'Es. sistemato il prezzo, avvisato il cliente'];
        }
        if ($r->stage === 'fatta' && (int) $r->isExtra === 1 && $r->installmentId === null) {
            $actions[] = ['label' => 'Nello scadenzario', 'action' => "{$base}/in-conto", 'variant' => 'primary', 'icon' => 'coins'];
        }

        return [
            'id' => (int) $r->id,
            'href' => $base,
            'customer' => $names[(int) $r->customerId] ?? '',
            'customerHref' => '/clienti/' . $r->customerId,
            'summary' => (string) $r->summary,
            'meta' => implode(' · ', array_filter([
                $r->channel,
                $r->receivedAt !== null ? F::shortDate(substr($r->receivedAt, 0, 10), $this->today) : null,
                $r->kind,
                $r->dueDate !== null && $r->isOpen() ? ($late ? 'in ritardo, era per ' : 'entro ') . F::shortDate($r->dueDate, $this->today) : null,
                $r->resolution,
            ])),
            'who' => $r->assignedUserId !== null ? ($people[(int) $r->assignedUserId] ?? null) : null,
            'priority' => $r->priority,
            'tone' => $late ? 'danger' : ($r->priority === 'alta' ? 'warning' : 'neutral'),
            'extra' => (int) $r->isExtra === 1 ? ($r->extraAmount !== null ? F::euro((float) $r->extraAmount) . ' + IVA' : 'extra') : null,
            'actions' => $actions,
        ];
    }

    /** @return array<int,string> */
    private function customerNames(): array
    {
        $names = [];
        foreach ($this->container->get(CustomerRepository::class)->findAll() as $customer) {
            $names[(int) $customer->id] = $customer->displayName();
        }

        return $names;
    }

    /** @return array<int,string> */
    private function people(): array
    {
        $result = [];
        foreach ($this->container->get(UserRepository::class)->findAll() as $user) {
            $result[(int) $user->id] = trim("{$user->firstName} {$user->lastName}");
        }

        return $result;
    }
}

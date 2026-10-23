<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\Expense;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Repository\CustomerRepository;
use App\Repository\ExpenseRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Support\ItalianFormat as F;
use App\View\Component\SimpleBoardComponent;
use App\View\Component\StatBoxComponent;

/** Spese: le mie; a chi approva, quelle da approvare e da rimborsare. */
final class ShowExpensesTool implements PromptToolInterface, PromptConversationalInterface
{
    private Container $container;
    private AuthService $auth;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->auth = $container->get(AuthService::class);
    }

    public function name(): string
    {
        return 'show_expenses';
    }

    public function description(): string
    {
        return "Spese di lavoro (carburante, parcheggi, modelle, materiali): le mie, quelle da approvare e da rimborsare. Usalo per 'spese', 'rimborsi', 'nota spese'.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Spese';
    }

    public function menuSection(): ?string
    {
        return 'vendite';
    }

    public function menuCount(): ?string
    {
        if (!$this->auth->hasPermission('expenses.approve')) {
            return null;
        }
        $count = count($this->container->get(ExpenseRepository::class)->findAll(['stage' => 'da approvare']));

        return $count > 0 ? $count . ' da approvare' : null;
    }

    public function requiredPermission(): ?string
    {
        return 'expenses.view';
    }

    public function triggers(): array
    {
        return ['spese', 'nota spese', 'rimborsi', 'rimborso spese'];
    }

    public function execute(array $input): array
    {
        $repo = $this->container->get(ExpenseRepository::class);
        $approver = $this->auth->hasPermission('expenses.approve');
        $people = [];
        foreach ($this->container->get(UserRepository::class)->findAll() as $user) {
            $people[(int) $user->id] = trim("{$user->firstName} {$user->lastName}");
        }
        $customers = [];
        foreach ($this->container->get(CustomerRepository::class)->findAll() as $customer) {
            $customers[(int) $customer->id] = $customer->displayName();
        }
        $today = date('Y-m-d');
        $row = static function (Expense $e, bool $withName, array $actions = []) use ($people, $customers, $today): array {
            return [
                'href' => '/spese/' . $e->id,
                'title' => ($withName ? ($people[(int) $e->userId] ?? '') . ' · ' : '') . ucfirst((string) $e->kind) . ($e->description ? ': ' . $e->description : ''),
                'meta' => implode(' · ', array_filter([
                    F::shortDate($e->expenseDate, $today),
                    $e->customerId !== null ? ($customers[(int) $e->customerId] ?? null) : null,
                    $e->paidBy,
                    (int) $e->rebill === 1 ? 'da riaddebitare' : null,
                    $e->receiptLink ? null : 'senza scontrino',
                ])),
                'amount' => F::euro((float) $e->amount),
                'badge' => ['label' => (string) $e->stage, 'tone' => match ($e->stage) { 'approvata' => 'info', 'rimborsata' => 'success', 'rifiutata' => 'danger', default => 'warning' }],
                'actions' => $actions,
            ];
        };

        $sections = [];
        $stats = [];
        if ($approver) {
            $pending = $repo->findAll(['stage' => 'da approvare'], 'expense_date, id');
            $toPay = array_filter($repo->findAll(['stage' => 'approvata'], 'expense_date, id'), static fn (Expense $e) => $e->toReimburse());
            $perMonth = $repo->totalPerMonth(6);
            $stats = [
                $this->container->get(StatBoxComponent::class)->toData([
                    'label' => 'Da approvare',
                    'value' => F::euro(array_sum(array_map(static fn (Expense $e) => (float) $e->amount, $pending))),
                    'comparison' => F::plural(count($pending), 'spesa', 'spese'),
                    'feature' => true,
                    'badge' => 'Adesso',
                ]),
                $this->container->get(StatBoxComponent::class)->toData([
                    'label' => 'Da rimborsare',
                    'value' => F::euro(array_sum(array_map(static fn (Expense $e) => (float) $e->amount, $toPay))),
                    'comparison' => 'approvate, pagate con soldi propri',
                    'dotColor' => $toPay !== [] ? 'brass' : 'moss',
                ]),
                $this->container->get(StatBoxComponent::class)->toData([
                    'label' => 'Spese di ' . F::monthName((int) date('n')),
                    'value' => F::euro($perMonth[date('Y-m')] ?? 0),
                    'comparison' => 'ultimi 6 mesi',
                    'dotColor' => 'moss',
                    'sparkline' => array_values($perMonth),
                    'sparklineStyle' => 'bars',
                ]),
            ];
            $sections[] = [
                'title' => 'Da approvare',
                'tone' => $pending !== [] ? 'warning' : 'neutral',
                'empty' => 'Nessuna spesa da approvare.',
                'rows' => array_map(static fn (Expense $e) => $row($e, true, [
                    ['label' => 'Approva', 'action' => '/spese/' . $e->id . '/decidi', 'body' => ['esito' => 'si'], 'variant' => 'primary', 'icon' => 'check'],
                    ['label' => 'No', 'action' => '/spese/' . $e->id . '/decidi', 'body' => ['esito' => 'no'], 'variant' => 'ghost', 'confirm' => 'Rifiutare la spesa?'],
                ]), $pending),
            ];
            $sections[] = [
                'title' => 'Da rimborsare',
                'tone' => 'info',
                'empty' => 'Niente da rimborsare.',
                'rows' => array_values(array_map(static fn (Expense $e) => $row($e, true, [
                    ['label' => 'Rimborsata', 'action' => '/spese/' . $e->id . '/rimborsata', 'variant' => 'secondary', 'icon' => 'coins'],
                ]), $toPay)),
            ];
        }
        $sections[] = [
            'title' => 'Le mie spese',
            'tone' => 'neutral',
            'empty' => 'Nessuna spesa segnata.',
            'rows' => array_map(static fn (Expense $e) => $row($e, false), $repo->findForUser((int) $this->auth->currentUserId(), 15)),
        ];

        return [$this->container->get(SimpleBoardComponent::class)->toData([
            'title' => 'Spese',
            'url' => '/spese',
            'createHref' => '/spese/nuovo',
            'createLabel' => 'Segna una spesa',
            'stats' => $stats,
            'sections' => $sections,
        ])];
    }

    public function replySummary(array $input): ?string
    {
        $pending = count($this->container->get(ExpenseRepository::class)->findAll(['stage' => 'da approvare']));

        return $this->auth->hasPermission('expenses.approve') && $pending > 0 ? F::plural($pending, 'spesa da approvare', 'spese da approvare') . '.' : 'Ecco le spese.';
    }

    public function followUpSuggestions(): array
    {
        return ['Segna una spesa', 'Commesse'];
    }
}

<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Model\Expense;
use App\Prompt\Tool\EditExpenseTool;
use App\Prompt\Tool\ShowExpensesTool;
use App\Repository\ExpenseRepository;
use App\Service\AuthService;

/**
 * Spese. Chi non approva segna e vede solo le sue; chi approva decide e
 * segna i rimborsi con un clic.
 */
final class ExpensesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return ExpenseRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditExpenseTool::class;
    }

    protected function listToolClass(): string
    {
        return ShowExpensesTool::class;
    }

    protected function packageName(): string
    {
        return 'expenses';
    }

    protected function listPageTitle(): string
    {
        return 'Spese';
    }

    protected function newPageTitle(): string
    {
        return 'Segna una spesa';
    }

    protected function editPageTitle(): string
    {
        return 'Spesa';
    }

    protected function defaultsOnCreate(): array
    {
        return ['user_id' => (string) $this->container->get(AuthService::class)->currentUserId(), 'expense_date' => date('Y-m-d'), 'kind' => 'carburante', 'stage' => 'da approvare', 'paid_by' => 'soldi miei', 'rebill' => '0'];
    }

    protected function createdMessage(): string
    {
        return 'Spesa segnata: la vede chi approva.';
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return match ($action) {
            'decide', 'reimbursed', 'delete' => 'expenses.approve',
            default => 'expenses.view',
        };
    }

    public function editAction(): ?string
    {
        $auth = $this->container->get(AuthService::class);
        $expense = $this->repository()->find((int) $this->param('id'));
        if ($expense instanceof Expense && !$auth->hasPermission('expenses.approve') && $expense->userId !== $auth->currentUserId()) {
            $this->json(['ok' => false, 'message' => 'Puoi vedere solo le tue spese.'], 403);

            return null;
        }

        return parent::editAction();
    }

    protected function afterCreate(int $id): void
    {
        $auth = $this->container->get(AuthService::class);
        if (!$auth->hasPermission('expenses.approve')) {
            $this->repository()->update($id, ['user_id' => $auth->currentUserId(), 'stage' => 'da approvare']);
        }
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        if ($before instanceof Expense && !$this->container->get(AuthService::class)->hasPermission('expenses.approve')) {
            $this->repository()->update($id, ['stage' => $before->stage, 'user_id' => $before->userId, 'reimbursed_at' => $before->reimbursedAt]);
        }

        return null;
    }

    public function decideAction(): void
    {
        $expense = $this->repository()->find((int) $this->param('id'));
        if (!$expense instanceof Expense || $expense->stage !== 'da approvare') {
            $this->json(['ok' => false, 'message' => 'Questa spesa e\' gia\' stata decisa.'], 422);

            return;
        }
        $approve = $this->request->get('esito') === 'si';
        $this->repository()->update((int) $expense->id, [
            'stage' => $approve ? 'approvata' : 'rifiutata',
            'decided_by' => $this->container->get(AuthService::class)->currentUserId(),
            'decided_at' => date('Y-m-d H:i:s'),
        ]);
        $this->json(['ok' => true, 'message' => $approve ? ($expense->toReimburse() ? 'Approvata: ora e\' da rimborsare.' : 'Approvata.') : 'Rifiutata.']);
    }

    public function reimbursedAction(): void
    {
        $this->repository()->update((int) $this->param('id'), ['stage' => 'rimborsata', 'reimbursed_at' => date('Y-m-d')]);
        $this->json(['ok' => true, 'message' => 'Segnata come rimborsata.']);
    }
}

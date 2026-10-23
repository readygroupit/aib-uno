<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\ExpenseRepository;

final class EditExpenseTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return ExpenseRepository::class;
    }

    protected function packageName(): string
    {
        return 'expenses';
    }

    protected function entityTag(): string
    {
        return 'expense';
    }

    protected function baseUrl(): string
    {
        return '/spese';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Segna una spesa' : 'Spesa';
    }

    protected function systemManagedFields(): array
    {
        return $this->auth->hasPermission('expenses.approve') ? ['decided_by', 'decided_at'] : ['decided_by', 'decided_at', 'stage', 'reimbursed_at', 'user_id'];
    }

    protected function searchColumns(): array
    {
        return ['description', 'kind'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'kind', 'label' => 'Tipo'], ['key' => 'amount', 'label' => 'Importo'], ['key' => 'expenseDate', 'label' => 'Quando']];
    }

    public function name(): string
    {
        return 'edit_expense';
    }

    public function description(): string
    {
        return "Segna una spesa di lavoro (carburante, parcheggio, modelle, materiali) o ne apre una.";
    }

    public function requiredPermission(): ?string
    {
        return 'expenses.view';
    }
}

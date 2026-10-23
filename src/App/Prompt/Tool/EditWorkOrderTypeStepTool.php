<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\WorkOrderTypeStepRepository;

final class EditWorkOrderTypeStepTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return WorkOrderTypeStepRepository::class;
    }

    protected function packageName(): string
    {
        return 'work_orders';
    }

    protected function manifestEntityKey(): string
    {
        return 'work_order_type_steps';
    }

    protected function entityTag(): string
    {
        return 'work_order_type_step';
    }

    protected function baseUrl(): string
    {
        return '/tipi-commessa/fasi';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuova fase' : 'Fase del modello';
    }

    protected function searchColumns(): array
    {
        return ['title'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'title', 'label' => 'Fase'], ['key' => 'position', 'label' => 'Ordine']];
    }

    public function name(): string
    {
        return 'edit_work_order_type_step';
    }

    public function description(): string
    {
        return "Apre una fase di un tipo di commessa.";
    }

    public function requiredPermission(): ?string
    {
        return 'work_orders.edit';
    }
}

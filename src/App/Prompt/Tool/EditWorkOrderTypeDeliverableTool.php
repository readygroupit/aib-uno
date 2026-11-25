<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Model\AbstractModel;
use App\Repository\WorkOrderTypeDeliverableRepository;
use App\Repository\WorkOrderTypeRepository;

final class EditWorkOrderTypeDeliverableTool extends AbstractProcessChildTool
{
    protected function repositoryClass(): string
    {
        return WorkOrderTypeDeliverableRepository::class;
    }

    protected function manifestEntityKey(): string
    {
        return 'work_order_type_deliverables';
    }

    protected function entityTag(): string
    {
        return 'work_order_type_deliverable';
    }

    protected function baseUrl(): string
    {
        return '/tipi-commessa/consegne';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Cosa si consegna' : 'Cosa si consegna';
    }

    protected function hiddenFields(): array
    {
        return ['work_order_type_id'];
    }

    protected function parentPage(AbstractModel $model, string $message): ?array
    {
        $type = $this->container->get(WorkOrderTypeRepository::class)->find((int) $model->workOrderTypeId);

        return $type !== null ? $this->container->get(EditWorkOrderTypeTool::class)->buildPage($type, $message, 'success') : null;
    }

    protected function dynamicOptions(AbstractModel $model): array
    {
        return [];
    }

    public function name(): string
    {
        return 'edit_work_order_type_deliverable';
    }

    public function description(): string
    {
        return "Apre una delle cose da consegnare di un processo.";
    }
}

<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\AbstractModel;
use App\Model\WorkOrderType;
use App\Model\WorkOrderTypeStep;
use App\Repository\WorkOrderTypeRepository;
use App\Repository\WorkOrderTypeStepRepository;
use App\View\Component\TypeStepsComponent;
use App\Work\WorkOrderOverviewService;

final class EditWorkOrderTypeTool extends AbstractEditEntityTool
{
    private WorkOrderTypeStepRepository $steps;
    private TypeStepsComponent $stepsComponent;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->steps = $container->get(WorkOrderTypeStepRepository::class);
        $this->stepsComponent = $container->get(TypeStepsComponent::class);
    }

    protected function repositoryClass(): string
    {
        return WorkOrderTypeRepository::class;
    }

    protected function packageName(): string
    {
        return 'work_orders';
    }

    protected function manifestEntityKey(): string
    {
        return 'work_order_types';
    }

    protected function entityTag(): string
    {
        return 'work_order_type';
    }

    protected function baseUrl(): string
    {
        return '/tipi-commessa';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo tipo di commessa' : 'Tipo di commessa';
    }

    protected function formTitleBelowDetail(): ?string
    {
        return 'Nome e durata';
    }

    protected function searchColumns(): array
    {
        return ['name'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'name', 'label' => 'Tipo']];
    }

    protected function detailComponents(AbstractModel $model): array
    {
        if (!$model instanceof WorkOrderType) {
            return [];
        }

        $steps = $this->steps->findByType((int) $model->id);
        $total = array_sum(array_map(static fn (WorkOrderTypeStep $s) => (int) $s->estimatedMinutes, $steps));

        return [$this->stepsComponent->toData([
            'url' => '/tipi-commessa/' . $model->id,
            'name' => (string) $model->name,
            'description' => $model->description,
            'days' => $model->defaultDays,
            'total' => $total > 0 ? WorkOrderOverviewService::duration($total) : null,
            'steps' => array_map(static fn (WorkOrderTypeStep $s) => [
                'href' => '/tipi-commessa/fasi/' . $s->id,
                'position' => $s->position,
                'title' => (string) $s->title,
                'day' => $s->offsetDays !== null ? 'giorno ' . $s->offsetDays : null,
                'estimate' => $s->estimatedMinutes ? WorkOrderOverviewService::duration((int) $s->estimatedMinutes) : null,
                'waits' => (int) $s->waitsForClient === 1,
                'checklist' => count(array_filter(array_map('trim', explode("\n", (string) $s->checklist)))),
            ], $steps),
            'addHref' => '/tipi-commessa/fasi/nuovo?work_order_type_id=' . $model->id . '&position=' . (count($steps) + 1),
            'newOrderHref' => '/commesse/nuovo?work_order_type_id=' . $model->id,
        ])];
    }

    public function name(): string
    {
        return 'edit_work_order_type';
    }

    public function description(): string
    {
        return "Apre un tipo di commessa con le sue fasi a modello.";
    }

    public function requiredPermission(): ?string
    {
        return 'work_orders.edit';
    }
}

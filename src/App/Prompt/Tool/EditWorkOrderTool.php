<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\AbstractModel;
use App\Model\WorkOrder;
use App\Repository\WorkOrderRepository;
use App\View\Component\WorkOrderSummaryComponent;
use App\Work\WorkOrderOverviewService;

final class EditWorkOrderTool extends AbstractEditEntityTool
{
    private WorkOrderOverviewService $overview;
    private WorkOrderSummaryComponent $summary;

    private Container $container;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->container = $container;
        $this->overview = $container->get(WorkOrderOverviewService::class);
        $this->summary = $container->get(WorkOrderSummaryComponent::class);
    }

    protected function repositoryClass(): string
    {
        return WorkOrderRepository::class;
    }

    protected function packageName(): string
    {
        return 'work_orders';
    }

    protected function entityTag(): string
    {
        return 'work_order';
    }

    protected function baseUrl(): string
    {
        return '/commesse';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuova commessa' : 'Modifica commessa';
    }

    protected function formTitleBelowDetail(): ?string
    {
        return 'Dati della commessa';
    }

    protected function searchColumns(): array
    {
        return ['title'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Commessa'],
            ['key' => 'stage', 'label' => 'Stato'],
            ['key' => 'dueDate', 'label' => 'Consegna'],
        ];
    }

    protected function systemManagedFields(): array
    {
        return ['last_reminder_at', 'last_activity_at'];
    }

    protected function detailComponents(AbstractModel $model): array
    {
        if (!$model instanceof WorkOrder) {
            return [];
        }

        $detail = $this->overview->detail($model);
        $card = $detail['card'];
        $base = '/commesse/' . $model->id;

        $actions = [];
        if ($model->stage === 'in attesa del cliente') {
            $actions[] = ['label' => 'Ho sollecitato', 'action' => "{$base}/sollecitato", 'variant' => 'secondary', 'icon' => 'bell'];
            $actions[] = ['label' => 'Il cliente ha risposto', 'action' => "{$base}/riprendi", 'variant' => 'primary', 'icon' => 'check'];
        } elseif ($model->isActive()) {
            $actions[] = ['label' => 'Aspettiamo il cliente', 'action' => "{$base}/attesa", 'variant' => 'secondary', 'ask' => 'Cosa aspettiamo dal cliente?', 'icon' => 'clock'];
            if ($model->stage === 'in pausa') {
                $actions[] = ['label' => 'Riprendi', 'action' => "{$base}/riprendi", 'variant' => 'secondary'];
            } else {
                $actions[] = ['label' => 'Metti in pausa', 'action' => "{$base}/pausa", 'variant' => 'ghost'];
            }
        }
        if ($model->isActive()) {
            $allDone = $card['progress']['total'] > 0 && $card['progress']['done'] === $card['progress']['total'];
            $actions[] = [
                'label' => 'Consegnata',
                'action' => "{$base}/consegnata",
                'variant' => $allDone ? 'primary' : 'secondary',
                'confirm' => $allDone ? null : "Ci sono ancora fasi da fare. Segnare comunque la commessa come consegnata?",
                'icon' => 'circle-check',
            ];
        }

        $appointments = null;
        if (in_array('appointments', \App\Package\PackageCatalogService::installedNames(), true)) {
            $calendar = $this->container->get(\App\Calendar\CalendarOverviewService::class);
            $appointments = $calendar->shortList($this->container->get(\App\Repository\AppointmentRepository::class)->findForEntity('work_orders', (int) $model->id));
        }

        return [$this->summary->toData([
            'url' => $base,
            'appointments' => $appointments,
            'addAppointmentHref' => $appointments !== null
                ? '/appuntamenti/nuovo?entity_type=work_orders&entity_id=' . $model->id . '&customer_id=' . $model->customerId . '&title=' . rawurlencode((string) $model->title)
                : null,
            'card' => $card,
            'steps' => $detail['steps'],
            'hours' => $detail['hours'],
            'expenses' => $detail['expenses'],
            'newExpenseHref' => $detail['newExpenseHref'],
            'supplier' => $detail['supplier'],
            'notes' => $detail['notes'],
            'actions' => $actions,
            'addStepHref' => '/attivita/nuovo?entity_type=work_orders&entity_id=' . $model->id
                . ($model->ownerUserId !== null ? '&assigned_user_id=' . $model->ownerUserId : '')
                . '&position=' . ($card['progress']['total'] + 1),
        ])];
    }

    public function name(): string
    {
        return 'edit_work_order';
    }

    public function description(): string
    {
        return "Apre una commessa (con fasi, avanzamento, attese del cliente), cercata per id o titolo.";
    }

    public function requiredPermission(): ?string
    {
        return 'work_orders.edit';
    }
}

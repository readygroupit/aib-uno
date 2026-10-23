<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\AbstractModel;
use App\Model\Task;
use App\Package\PackageCatalogService;
use App\Repository\CustomerRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\WorkOrderRepository;
use App\Support\ItalianFormat as F;
use App\View\Component\TaskSummaryComponent;
use App\Work\WorkOrderOverviewService;
use App\Work\WorkOrderService;

final class EditTaskTool extends AbstractEditEntityTool
{
    private TaskSummaryComponent $summary;
    private Container $container;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->container = $container;
        $this->summary = $container->get(TaskSummaryComponent::class);
    }

    protected function repositoryClass(): string
    {
        return TaskRepository::class;
    }

    protected function packageName(): string
    {
        return 'tasks';
    }

    protected function entityTag(): string
    {
        return 'task';
    }

    protected function baseUrl(): string
    {
        return '/attivita';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? "Nuova attivita'" : "Modifica attivita'";
    }

    protected function formTitleBelowDetail(): ?string
    {
        return "Dettagli dell'attivita'";
    }

    protected function searchColumns(): array
    {
        return ['title', 'description'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titolo'],
            ['key' => 'plannedDate', 'label' => 'Da fare il'],
            ['key' => 'stage', 'label' => 'Stato'],
        ];
    }

    /** A cosa e' collegata (commessa, cliente) arriva dal pulsante che la crea, non si sceglie a mano. */
    protected function hiddenFields(): array
    {
        return ['entity_type', 'entity_id', 'source_step_id'];
    }

    protected function detailComponents(AbstractModel $model): array
    {
        if (!$model instanceof Task) {
            return [];
        }

        $context = null;
        $installed = PackageCatalogService::installedNames();
        if ($model->entityType === WorkOrderService::ENTITY && $model->entityId !== null && in_array('work_orders', $installed, true)) {
            $order = $this->container->get(WorkOrderRepository::class)->find((int) $model->entityId);
            if ($order !== null) {
                $context = ['label' => 'Commessa: ' . $order->title, 'href' => '/commesse/' . $order->id];
            }
        } elseif ($model->entityType === 'customers' && $model->entityId !== null && in_array('customers', $installed, true)) {
            $customer = $this->container->get(CustomerRepository::class)->find((int) $model->entityId);
            if ($customer !== null) {
                $context = ['label' => 'Cliente: ' . $customer->displayName(), 'href' => '/clienti/' . $customer->id];
            }
        }

        $who = null;
        if ($model->assignedUserId !== null) {
            $user = $this->container->get(UserRepository::class)->find((int) $model->assignedUserId);
            $who = $user !== null ? trim("{$user->firstName} {$user->lastName}") : null;
        }

        $today = date('Y-m-d');
        $day = $model->day();
        $lines = array_values(array_filter(array_map('trim', explode("\n", (string) $model->checklist)), static fn (string $l) => $l !== ''));

        return [$this->summary->toData([
            'url' => '/attivita/' . $model->id,
            'title' => (string) $model->title,
            'done' => !$model->isOpen(),
            'description' => $model->description,
            'context' => $context,
            'facts' => array_values(array_filter([
                $day !== null ? ['icon' => 'calendar', 'text' => 'Da fare il ' . F::shortDate($day, $today) . ' (' . F::relativeDays($day, $today) . ')'] : null,
                $model->dueAt !== null ? ['icon' => 'clock', 'text' => 'Scadenza ' . F::shortDate(substr($model->dueAt, 0, 10), $today) . ' ' . substr($model->dueAt, 11, 5)] : null,
                $who !== null ? ['icon' => 'user', 'text' => $who] : null,
                $model->estimatedMinutes ? ['icon' => 'clock', 'text' => 'Previsto ' . WorkOrderOverviewService::duration((int) $model->estimatedMinutes) . ($model->actualMinutes ? ', impiegato ' . WorkOrderOverviewService::duration((int) $model->actualMinutes) : '')] : null,
                $model->recurrence !== null ? ['icon' => 'arrows-rotate', 'text' => 'Si ripete ' . str_replace('ogni ', 'ogni ', $model->recurrence)] : null,
            ])),
            'tags' => array_values(array_filter([
                $model->priority === 'alta' ? ['label' => 'Priorita alta', 'tone' => 'danger'] : null,
                (int) $model->waitsForClient === 1 ? ['label' => 'Aspetta il cliente', 'tone' => 'warning'] : null,
            ])),
            'checklist' => array_map(static fn (string $l) => [
                'text' => trim(preg_replace('/^\[x\]\s*/i', '', $l)),
                'done' => str_starts_with(strtolower($l), '[x]'),
            ], $lines),
            'toggleAction' => '/attivita/' . $model->id . '/fatta',
        ])];
    }

    public function name(): string
    {
        return 'edit_task';
    }

    public function description(): string
    {
        return "Apre un'attivita' (anche una fase di commessa), cercata per id o per titolo/descrizione.";
    }

    public function requiredPermission(): ?string
    {
        return 'tasks.edit';
    }
}

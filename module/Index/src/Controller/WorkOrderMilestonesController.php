<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Prompt\Tool\EditWorkOrderMilestoneTool;
use App\Prompt\Tool\ListWorkOrdersTool;
use App\Repository\WorkOrderMilestoneRepository;

final class WorkOrderMilestonesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return WorkOrderMilestoneRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditWorkOrderMilestoneTool::class;
    }

    protected function listToolClass(): string
    {
        return ListWorkOrdersTool::class;
    }

    protected function packageName(): string
    {
        return 'work_orders';
    }

    protected function manifestEntityKey(): string
    {
        return 'work_order_milestones';
    }

    protected function listPageTitle(): string
    {
        return 'Commesse';
    }

    protected function newPageTitle(): string
    {
        return 'Nuova data chiave';
    }

    protected function editPageTitle(): string
    {
        return 'Fissa la data';
    }

    protected function defaultsOnCreate(): array
    {
        return ['position' => '99'];
    }

    protected function createdMessage(): string
    {
        return 'Data chiave aggiunta.';
    }

    /** Appuntamento, email al cliente (una volta), fase chiusa, squadra avvisata. */
    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        $previous = $before instanceof \App\Model\WorkOrderMilestone ? $before->scheduledAt : null;

        return $this->container->get(\App\Work\ProcessService::class)->onMilestoneSaved($id, $previous);
    }

    protected function afterCreate(int $id): void
    {
        $this->container->get(\App\Work\ProcessService::class)->onMilestoneSaved($id, null);
    }

    protected function crossFieldErrors(array $data): array
    {
        if (($data['send_client_email'] ?? null) === '1' && ($data['scheduled_at'] ?? null) !== null && trim((string) ($data['client_email_to'] ?? '')) === '') {
            return ['client_email_to' => "A chi mandiamo l'email? Scrivi l'indirizzo del cliente, oppure spegni l'invio."];
        }

        return [];
    }
}

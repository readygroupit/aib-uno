<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditWorkOrderChangeRequestTool;
use App\Prompt\Tool\ListWorkOrdersTool;
use App\Repository\WorkOrderChangeRequestRepository;

final class WorkOrderChangeRequestsController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return WorkOrderChangeRequestRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditWorkOrderChangeRequestTool::class;
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
        return 'work_order_change_requests';
    }

    protected function listPageTitle(): string
    {
        return 'Commesse';
    }

    protected function newPageTitle(): string
    {
        return 'Modifica chiesta dal cliente';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica chiesta dal cliente';
    }

    protected function defaultsOnCreate(): array
    {
        return ['stage' => 'aperta', 'is_extra' => '0', 'requested_at' => date('Y-m-d')];
    }

    protected function createdMessage(): string
    {
        return 'Modifica registrata.';
    }

    /** Chi la fa (o, se nessuno, la squadra) viene avvisato. */
    protected function afterCreate(int $id): void
    {
        $request = $this->repository()->find($id);
        if (!$request instanceof \App\Model\WorkOrderChangeRequest) {
            return;
        }
        $order = $this->container->get(\App\Repository\WorkOrderRepository::class)->find((int) $request->workOrderId);
        if (!$order instanceof \App\Model\WorkOrder) {
            return;
        }
        $people = $request->assignedUserId !== null
            ? [(int) $request->assignedUserId]
            : array_map(static fn ($m) => (int) $m->userId, $this->container->get(\App\Repository\WorkOrderMemberRepository::class)->findByWorkOrder((int) $order->id));
        $notifications = $this->container->get(\App\Notifications\NotificationService::class);
        foreach (array_unique($people) as $userId) {
            $notifications->notify(
                $userId,
                \App\Notifications\NotificationTypes::CHANGE_REQUEST,
                ((int) $request->isExtra === 1 ? 'Modifica extra' : 'Modifica') . " chiesta dal cliente: {$order->title}",
                mb_substr((string) $request->description, 0, 300),
                '/commesse/' . $order->id,
                'work_orders',
                (int) $order->id
            );
        }
        $this->container->get(\App\Repository\WorkOrderRepository::class)->update((int) $order->id, ['last_activity_at' => date('Y-m-d H:i:s')]);
    }
}

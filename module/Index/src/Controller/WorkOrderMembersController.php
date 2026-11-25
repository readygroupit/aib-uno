<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Prompt\Tool\EditWorkOrderMemberTool;
use App\Prompt\Tool\ListWorkOrdersTool;
use App\Repository\WorkOrderMemberRepository;

final class WorkOrderMembersController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return WorkOrderMemberRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditWorkOrderMemberTool::class;
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
        return 'work_order_members';
    }

    protected function listPageTitle(): string
    {
        return 'Commesse';
    }

    protected function newPageTitle(): string
    {
        return 'Aggiungi alla squadra';
    }

    protected function editPageTitle(): string
    {
        return 'Nella squadra';
    }

    protected function defaultsOnCreate(): array
    {
        return [];
    }

    protected function createdMessage(): string
    {
        return 'Aggiunto alla squadra.';
    }

    /** Chi entra viene avvisato e prende le fasi del suo ruolo ancora senza nessuno. */
    protected function afterCreate(int $id): void
    {
        $member = $this->repository()->find($id);
        if (!$member instanceof \App\Model\WorkOrderMember) {
            return;
        }
        $order = $this->container->get(\App\Repository\WorkOrderRepository::class)->find((int) $member->workOrderId);
        if ($order instanceof \App\Model\WorkOrder) {
            $process = $this->container->get(\App\Work\ProcessService::class);
            $process->notifyMember($order, $member);
            $process->reassign((int) $order->id);
        }
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        $member = $this->repository()->find($id);
        if ($member instanceof \App\Model\WorkOrderMember) {
            $moved = $this->container->get(\App\Work\ProcessService::class)->reassign((int) $member->workOrderId);

            return $moved > 0 ? "Salvato: {$moved} fasi passate a chi ha il ruolo." : 'Salvato.';
        }

        return null;
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return in_array($action, ['assign', 'remove'], true) ? 'work_orders.edit' : parent::requiredPermissionForAction($action);
    }

    /** Dalla fase "Comporre la squadra": una persona in un ruolo. */
    public function assignAction(): void
    {
        $message = $this->container->get(\App\Work\ProcessService::class)->assignRole(
            (int) $this->param('id'),
            (int) $this->request->get('role_id', 0),
            (int) $this->request->get('user_id', 0)
        );
        $ok = !in_array($message, ['Ruolo non trovato.'], true) && (int) $this->request->get('user_id', 0) > 0;
        $this->json(['ok' => $ok, 'message' => $ok ? $message : 'Scegli una persona.'], $ok ? 200 : 422);
    }

    /** Dalla fase "Comporre la squadra": toglie una persona. */
    public function removeAction(): void
    {
        $message = $this->container->get(\App\Work\ProcessService::class)->removeMember((int) $this->param('id'));
        $this->json(['ok' => $message !== 'Persona non trovata.', 'message' => $message]);
    }
}

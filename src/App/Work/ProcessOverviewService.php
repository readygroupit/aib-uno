<?php

declare(strict_types=1);

namespace App\Work;

use App\Core\Container;
use App\Model\Task;
use App\Model\WorkOrder;
use App\Model\WorkOrderChangeRequest;
use App\Model\WorkOrderDeliverable;
use App\Model\WorkOrderMember;
use App\Model\WorkOrderMilestone;
use App\Model\WorkOrderTypeRole;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\WorkOrderChangeRequestRepository;
use App\Repository\WorkOrderDeliverableRepository;
use App\Repository\WorkOrderMemberRepository;
use App\Repository\WorkOrderMilestoneRepository;
use App\Repository\WorkOrderTypeRoleRepository;
use App\Repository\WorkOrderTypeStepRepository;
use App\Service\AuthService;
use App\Support\ItalianFormat as F;

/**
 * Le parti del progetto che vengono dal processo, per la scheda: squadra
 * (con i ruoli ancora scoperti), date chiave, fasi raggruppate con chi le
 * fa e i pulsanti di approvazione, cosa si consegna, modifiche del cliente.
 */
final class ProcessOverviewService
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param array<int,array<string,mixed>> $steps le fasi gia' pronte per la scheda (WorkOrderOverviewService::detail())
     * @return array<string,mixed>
     */
    public function forOrder(WorkOrder $order, array $steps): array
    {
        $orderId = (int) $order->id;
        $typeId = $order->workOrderTypeId !== null ? (int) $order->workOrderTypeId : null;
        $roles = [];
        $modelSteps = [];
        if ($typeId !== null) {
            foreach ($this->container->get(WorkOrderTypeRoleRepository::class)->findByWorkOrderType($typeId) as $role) {
                $roles[(int) $role->id] = $role;
            }
            foreach ($this->container->get(WorkOrderTypeStepRepository::class)->findByType($typeId) as $step) {
                $modelSteps[(int) $step->id] = $step;
            }
        }
        $users = $this->userNames();
        $avatars = [];
        foreach ($this->container->get(UserRepository::class)->findAll() as $user) {
            $avatars[(int) $user->id] = \App\Service\UserAvatarService::url($user->avatarPath);
        }
        $tasks = [];
        foreach ($this->container->get(TaskRepository::class)->findForEntity(WorkOrderService::ENTITY, $orderId) as $task) {
            $tasks[(int) $task->id] = $task;
        }
        $auth = $this->container->get(AuthService::class);
        $me = (int) $auth->currentUserId();
        $canManage = $auth->hasPermission('work_orders.edit');

        $decorated = array_map(function (array $item) use ($tasks, $modelSteps, $roles, $me, $canManage) {
            $task = $tasks[(int) $item['id']] ?? null;
            $step = $task instanceof Task && $task->sourceStepId !== null ? ($modelSteps[(int) $task->sourceStepId] ?? null) : null;
            $item['phase'] = $step?->phase;
            $item['roleLabel'] = $step !== null && $step->roleId !== null ? ($roles[(int) $step->roleId]->label ?? null) : null;
            $item['kind'] = $step?->kind;
            $item['optional'] = $step !== null && (int) $step->isOptional === 1;
            $item['skipped'] = $task instanceof Task && $task->stage === 'annullata';
            $item['actions'] = [];
            if ($task instanceof Task && $task->isOpen() && $step !== null && ((int) $task->assignedUserId === $me || $canManage)) {
                if ($step->kind === ProcessService::KIND_APPROVAL) {
                    $item['actions'][] = ['label' => 'Approva', 'action' => "/attivita/{$task->id}/approva", 'variant' => 'primary', 'icon' => 'thumbs-up'];
                    $item['actions'][] = ['label' => 'Rimanda indietro', 'action' => "/attivita/{$task->id}/rimanda", 'variant' => 'secondary', 'ask' => 'Cosa va sistemato?', 'placeholder' => 'Es. il logo e\' troppo simile alla concorrenza'];
                }
                $guided = ProcessService::guidedAction($step->kind, (int) $task->id);
                if ($guided !== null) {
                    $item['actions'][] = $guided;
                }
                if ((int) $step->isOptional === 1) {
                    $item['actions'][] = ['label' => 'Non la facciamo', 'action' => "/attivita/{$task->id}/salta", 'variant' => 'ghost', 'confirm' => "Saltare \u{ab}{$task->title}\u{bb}?"];
                }
            }

            return $item;
        }, $steps);

        return [
            'steps' => $decorated,
            'hasPhases' => array_filter($decorated, static fn (array $i) => $i['phase'] !== null) !== [],
            'team' => $this->team($order, $roles, $users, $avatars),
            'milestones' => $this->milestones($order),
            'deliverables' => $this->deliverables($orderId),
            'changes' => $this->changes($order, $users),
        ];
    }

    /** @param array<int,WorkOrderTypeRole> $roles */
    private function team(WorkOrder $order, array $roles, array $users, array $avatars = []): array
    {
        $members = $this->container->get(WorkOrderMemberRepository::class)->findByWorkOrder((int) $order->id);
        $covered = array_map(static fn (WorkOrderMember $m) => (int) $m->roleId, $members);
        $missing = [];
        foreach ($roles as $role) {
            if ((int) $role->isRequired === 1 && !in_array((int) $role->id, $covered, true)) {
                $missing[] = [
                    'label' => (string) $role->label,
                    'addHref' => "/commesse/squadra/nuovo?work_order_id={$order->id}&role_id={$role->id}",
                ];
            }
        }
        $rank = array_flip(array_keys($roles));
        usort($members, static fn (WorkOrderMember $a, WorkOrderMember $b) => [$rank[(int) $a->roleId] ?? 99, (int) $a->id] <=> [$rank[(int) $b->roleId] ?? 99, (int) $b->id]);

        return [
            'members' => array_map(static fn (WorkOrderMember $m) => [
                'avatar' => $avatars[(int) $m->userId] ?? null,
                'name' => $users[(int) $m->userId] ?? '—',
                'initials' => F::initials($users[(int) $m->userId] ?? ''),
                'role' => $m->roleId !== null ? ($roles[(int) $m->roleId]->label ?? null) : null,
                'href' => '/commesse/squadra/' . $m->id,
            ], $members),
            'missing' => $missing,
            'addHref' => '/commesse/squadra/nuovo?work_order_id=' . $order->id,
        ];
    }

    /**
     * Per la fase "Comporre la squadra": ogni ruolo del processo con chi lo
     * fa adesso, chi lo fa di solito e le persone che si possono scegliere.
     */
    public function teamEditor(WorkOrder $order): array
    {
        $roles = $this->container->get(\App\Repository\WorkOrderTypeRoleRepository::class)->findByWorkOrderType((int) $order->workOrderTypeId);
        $users = [];
        $avatars = [];
        foreach ($this->container->get(\App\Repository\UserRepository::class)->findAll([], 'first_name, last_name') as $user) {
            $users[(int) $user->id] = trim("{$user->firstName} {$user->lastName}") ?: (string) $user->username;
            $avatars[(int) $user->id] = \App\Service\UserAvatarService::url($user->avatarPath ?? null);
        }
        $members = $this->container->get(WorkOrderMemberRepository::class)->findByWorkOrder((int) $order->id);
        $rows = [];
        $missing = [];
        foreach ($roles as $role) {
            $people = array_values(array_filter($members, static fn (WorkOrderMember $m) => (int) $m->roleId === (int) $role->id));
            if ($people === [] && (int) $role->isRequired === 1) {
                $missing[] = (string) $role->label;
            }
            $rows[] = [
                'roleId' => (int) $role->id,
                'label' => (string) $role->label,
                'required' => (int) $role->isRequired === 1,
                'usual' => $role->defaultUserId !== null ? ($users[(int) $role->defaultUserId] ?? null) : null,
                'people' => array_map(static fn (WorkOrderMember $m) => [
                    'name' => $users[(int) $m->userId] ?? '—',
                    'initials' => F::initials($users[(int) $m->userId] ?? ''),
                    'avatar' => $avatars[(int) $m->userId] ?? null,
                    'removeAction' => '/commesse/squadra/' . $m->id . '/togli',
                ], $people),
            ];
        }

        return [
            'roles' => $rows,
            'missing' => $missing,
            'users' => array_map(static fn (int $id, string $name) => ['id' => $id, 'name' => $name], array_keys($users), $users),
            'assignAction' => '/commesse/' . $order->id . '/squadra/assegna',
        ];
    }

    private function milestones(WorkOrder $order): array
    {
        $today = date('Y-m-d');

        return array_map(static fn (WorkOrderMilestone $m) => [
            'label' => (string) $m->label,
            'href' => '/commesse/date/' . $m->id,
            'set' => $m->scheduledAt !== null,
            'when' => $m->scheduledAt !== null ? F::longDate((string) $m->scheduledAt) . ' alle ' . substr((string) $m->scheduledAt, 11, 5) : 'da fissare',
            'past' => $m->scheduledAt !== null && substr((string) $m->scheduledAt, 0, 10) < $today,
            'email' => (int) $m->sendClientEmail === 1 || $m->clientEmailSentAt !== null
                ? ($m->clientEmailSentAt !== null ? 'email mandata ' . F::relativeDays(substr((string) $m->clientEmailSentAt, 0, 10), $today) : 'parte un\'email al cliente')
                : null,
        ], $this->container->get(WorkOrderMilestoneRepository::class)->findByWorkOrder((int) $order->id));
    }

    /** Le cose da consegnare di un progetto (per la fase di consegna). */
    public function deliverablesFor(int $orderId): array
    {
        return $this->deliverables($orderId);
    }

    private function deliverables(int $orderId): array
    {
        $items = $this->container->get(WorkOrderDeliverableRepository::class)->findByWorkOrder($orderId);

        return [
            'items' => array_map(static fn (WorkOrderDeliverable $d) => [
                'label' => (string) $d->label,
                'description' => $d->description,
                'done' => (int) $d->isDone === 1,
                'href' => '/commesse/consegne/' . $d->id,
                'toggleAction' => '/commesse/consegne/' . $d->id . '/pronto',
            ], $items),
            'done' => count(array_filter($items, static fn (WorkOrderDeliverable $d) => (int) $d->isDone === 1)),
            'addHref' => '/commesse/consegne/nuovo?work_order_id=' . $orderId . '&position=' . (count($items) + 1),
        ];
    }

    private function changes(WorkOrder $order, array $users): array
    {
        $today = date('Y-m-d');
        $items = $this->container->get(WorkOrderChangeRequestRepository::class)->findByWorkOrder((int) $order->id);

        return [
            'items' => array_map(static fn (WorkOrderChangeRequest $c) => [
                'description' => (string) $c->description,
                'extra' => (int) $c->isExtra === 1,
                'stage' => (string) $c->stage,
                'when' => F::relativeDays((string) $c->requestedAt, $today),
                'who' => $c->assignedUserId !== null ? ($users[(int) $c->assignedUserId] ?? null) : null,
                'href' => '/commesse/modifiche/' . $c->id,
            ], $items),
            'extra' => count(array_filter($items, static fn (WorkOrderChangeRequest $c) => (int) $c->isExtra === 1)),
            'addHref' => '/commesse/modifiche/nuovo?work_order_id=' . $order->id,
        ];
    }

    /** @return array<int,string> */
    private function userNames(): array
    {
        $names = [];
        foreach ($this->container->get(UserRepository::class)->findAll() as $user) {
            $names[(int) $user->id] = trim("{$user->firstName} {$user->lastName}") ?: (string) $user->username;
        }

        return $names;
    }
}

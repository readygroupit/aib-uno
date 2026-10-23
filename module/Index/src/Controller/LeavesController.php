<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Leaves\LeaveService;
use App\Model\AbstractModel;
use App\Model\LeaveRequest;
use App\Prompt\Tool\EditLeaveRequestTool;
use App\Prompt\Tool\ShowLeavesTool;
use App\Repository\LeaveRequestRepository;
use App\Service\AuthService;

/**
 * Ferie e permessi. Chi non approva chiede solo per se' e vede solo le
 * sue; chi approva decide con un clic (con la risposta, se e' un no).
 */
final class LeavesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return LeaveRequestRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditLeaveRequestTool::class;
    }

    protected function listToolClass(): string
    {
        return ShowLeavesTool::class;
    }

    protected function manifestEntityKey(): string
    {
        return 'leave_requests';
    }

    protected function packageName(): string
    {
        return 'leaves';
    }

    protected function listPageTitle(): string
    {
        return 'Ferie e permessi';
    }

    protected function newPageTitle(): string
    {
        return 'Chiedi ferie o permesso';
    }

    protected function editPageTitle(): string
    {
        return 'Ferie o permesso';
    }

    protected function defaultsOnCreate(): array
    {
        return ['user_id' => (string) $this->container->get(AuthService::class)->currentUserId(), 'kind' => 'ferie', 'stage' => 'richiesta', 'start_date' => date('Y-m-d', strtotime('+7 days'))];
    }

    protected function createdMessage(): string
    {
        return 'Richiesta inviata: la vede chi approva.';
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return match ($action) {
            'decide' => 'leaves.approve',
            'delete' => 'leaves.approve',
            default => 'leaves.view',
        };
    }

    /** Solo le proprie, per chi non approva. */
    public function editAction(): ?string
    {
        $auth = $this->container->get(AuthService::class);
        $leave = $this->repository()->find((int) $this->param('id'));
        if ($leave instanceof LeaveRequest && !$auth->hasPermission('leaves.approve') && $leave->userId !== $auth->currentUserId()) {
            $this->json(['ok' => false, 'message' => 'Puoi vedere solo le tue richieste.'], 403);

            return null;
        }

        return parent::editAction();
    }

    protected function afterCreate(int $id): void
    {
        $auth = $this->container->get(AuthService::class);
        $leave = $this->repository()->find($id);
        $data = [];
        // Chi non approva chiede solo per se'; chi approva, per se', la approva gia'.
        if (!$auth->hasPermission('leaves.approve')) {
            $data = ['user_id' => $auth->currentUserId(), 'stage' => 'richiesta'];
        } elseif ($leave instanceof LeaveRequest && $leave->stage === 'approvata') {
            $data = ['decided_by' => $auth->currentUserId(), 'decided_at' => date('Y-m-d H:i:s')];
        }
        if ($leave instanceof LeaveRequest && $leave->endDate !== null && $leave->endDate < (string) $leave->startDate) {
            $data['end_date'] = $leave->startDate;
        }
        if ($data !== []) {
            $this->repository()->update($id, $data);
        }
    }

    /** Chi non approva non puo' cambiare stato o persona, nemmeno forzando il form. */
    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        if ($before instanceof LeaveRequest && !$this->container->get(AuthService::class)->hasPermission('leaves.approve')) {
            $this->repository()->update($id, ['stage' => $before->stage, 'user_id' => $before->userId, 'reply' => $before->reply]);
        }

        return null;
    }

    public function decideAction(): void
    {
        try {
            $leave = $this->container->get(LeaveService::class)->decide(
                (int) $this->param('id'),
                $this->request->get('esito') === 'si',
                (int) $this->container->get(AuthService::class)->currentUserId(),
                (string) $this->request->get('text', '')
            );
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);

            return;
        }
        $this->json(['ok' => true, 'message' => $leave->stage === 'approvata' ? 'Approvata: l\'organizer ne tiene conto.' : 'Rifiutata.']);
    }
}

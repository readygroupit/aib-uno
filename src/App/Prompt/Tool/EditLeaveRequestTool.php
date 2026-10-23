<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\LeaveRequestRepository;

final class EditLeaveRequestTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return LeaveRequestRepository::class;
    }

    protected function manifestEntityKey(): string
    {
        return 'leave_requests';
    }

    protected function packageName(): string
    {
        return 'leaves';
    }

    protected function entityTag(): string
    {
        return 'leave_request';
    }

    protected function baseUrl(): string
    {
        return '/ferie';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Chiedi ferie o un permesso' : 'Ferie o permesso';
    }

    /** Lo stato e la risposta li decide chi approva, con i pulsanti; chi chiede non li tocca. */
    protected function systemManagedFields(): array
    {
        return $this->auth->hasPermission('leaves.approve') ? ['decided_by', 'decided_at'] : ['decided_by', 'decided_at', 'stage', 'reply', 'user_id'];
    }

    protected function searchColumns(): array
    {
        return ['kind', 'note'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'kind', 'label' => 'Cosa'], ['key' => 'startDate', 'label' => 'Dal'], ['key' => 'stage', 'label' => 'Stato']];
    }

    public function name(): string
    {
        return 'edit_leave_request';
    }

    public function description(): string
    {
        return "Chiede ferie, un permesso o un ROL (o apre una richiesta gia' fatta).";
    }

    public function requiredPermission(): ?string
    {
        return 'leaves.view';
    }
}

<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\CustomerTeamMemberRepository;

/** Chi della squadra interna segue un cliente, e per cosa (social, grafica, video...). */
final class EditCustomerTeamMemberTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return CustomerTeamMemberRepository::class;
    }

    protected function packageName(): string
    {
        return 'customers';
    }

    protected function manifestEntityKey(): string
    {
        return 'customer_team_members';
    }

    protected function entityTag(): string
    {
        return 'customer_team_member';
    }

    protected function baseUrl(): string
    {
        return '/clienti/squadra';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Aggiungi alla squadra del cliente' : 'Squadra del cliente';
    }

    protected function searchColumns(): array
    {
        return ['role', 'notes'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'role', 'label' => 'Ruolo']];
    }

    public function name(): string
    {
        return 'edit_customer_team_member';
    }

    public function description(): string
    {
        return "Aggiunge o modifica chi della squadra segue un cliente (referente, social, grafica, video, sviluppo, ADV).";
    }

    public function requiredPermission(): ?string
    {
        return 'customers.edit';
    }
}

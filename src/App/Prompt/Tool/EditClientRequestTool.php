<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\ClientRequestRepository;

final class EditClientRequestTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return ClientRequestRepository::class;
    }

    protected function packageName(): string
    {
        return 'client_requests';
    }

    protected function entityTag(): string
    {
        return 'client_request';
    }

    protected function baseUrl(): string
    {
        return '/richieste';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Cosa ha chiesto il cliente?' : 'Richiesta del cliente';
    }

    protected function systemManagedFields(): array
    {
        return ['installment_id', 'task_id', 'done_at'];
    }

    protected function searchColumns(): array
    {
        return ['summary', 'details'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'summary', 'label' => 'Richiesta'],
            ['key' => 'stage', 'label' => 'Stato'],
        ];
    }

    public function name(): string
    {
        return 'edit_client_request';
    }

    public function description(): string
    {
        return "Registra una richiesta di un cliente (arrivata al telefono, su WhatsApp, per email) o ne apre una, cercata per id o testo.";
    }

    public function requiredPermission(): ?string
    {
        return 'client_requests.create';
    }
}

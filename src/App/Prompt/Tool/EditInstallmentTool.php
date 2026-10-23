<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\InstallmentRepository;

final class EditInstallmentTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return InstallmentRepository::class;
    }

    protected function packageName(): string
    {
        return 'installments';
    }

    protected function entityTag(): string
    {
        return 'installment';
    }

    protected function baseUrl(): string
    {
        return '/scadenzario';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo incasso atteso' : 'Incasso';
    }

    protected function searchColumns(): array
    {
        return ['description'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'description', 'label' => 'Descrizione'],
            ['key' => 'dueDate', 'label' => 'Scadenza'],
            ['key' => 'amount', 'label' => 'Importo'],
        ];
    }

    protected function systemManagedFields(): array
    {
        return ['reminder_sent_at'];
    }

    public function name(): string
    {
        return 'edit_installment';
    }

    public function description(): string
    {
        return "Apre un incasso dello scadenzario (acconto, saldo, rata, rinnovo) per modificarlo o segnarlo come incassato.";
    }

    public function requiredPermission(): ?string
    {
        return 'installments.edit';
    }
}

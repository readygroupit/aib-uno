<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\RefundRepository;

final class EditRefundTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return RefundRepository::class;
    }

    protected function packageName(): string
    {
        return 'refunds';
    }

    protected function entityTag(): string
    {
        return 'refund';
    }

    protected function baseUrl(): string
    {
        return '/rimborsi';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo rimborso' : 'Modifica rimborso';
    }

    protected function searchColumns(): array
    {
        return ['payment_status', 'invoice_reference', 'notes'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'amountClaimed', 'label' => 'Importo richiesto'],
            ['key' => 'paymentStatus', 'label' => 'Stato pagamento'],
        ];
    }

    public function name(): string
    {
        return 'edit_refund';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di un rimborso, cercato per id o per stato/riferimento fattura/note. "
            . "Usalo quando l'utente chiede di aprire o modificare un rimborso specifico.";
    }

    public function requiredPermission(): ?string
    {
        return 'refunds.edit';
    }
}

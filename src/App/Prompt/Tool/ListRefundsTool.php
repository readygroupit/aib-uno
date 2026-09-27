<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\RefundRepository;

final class ListRefundsTool extends AbstractListEntityTool
{
    protected function repositoryClass(): string
    {
        return RefundRepository::class;
    }

    protected function entityTag(): string
    {
        return 'refund';
    }

    protected function baseUrl(): string
    {
        return '/rimborsi';
    }

    protected function title(): string
    {
        return 'Rimborsi';
    }

    protected function statLabel(): string
    {
        return 'Totale rimborsi';
    }

    protected function createLabel(): ?string
    {
        return 'Nuovo rimborso';
    }

    protected function createPermission(): ?string
    {
        return 'refunds.create';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'amountClaimed', 'label' => 'Importo richiesto'],
            ['key' => 'amountAccepted', 'label' => 'Importo accettato'],
            ['key' => 'paymentStatus', 'label' => 'Stato pagamento', 'badge' => true],
        ];
    }

    protected function actions(): array
    {
        return [
            ['label' => 'Modifica', 'href' => '/rimborsi/{id}', 'permission' => 'refunds.edit'],
        ];
    }

    public function name(): string
    {
        return 'list_refunds';
    }

    public function description(): string
    {
        return "Mostra l'elenco dei rimborsi con statistica totale.";
    }

    public function menuLabel(): ?string
    {
        return 'Rimborsi';
    }

    public function menuSection(): ?string
    {
        return 'operativita';
    }

    public function requiredPermission(): ?string
    {
        return 'refunds.view';
    }

    public function triggers(): array
    {
        return ['rimborsi', 'lista rimborsi', 'elenco rimborsi', 'pagamenti'];
    }
}

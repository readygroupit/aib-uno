<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\CustomerTouchpointRepository;

final class EditCustomerTouchpointTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return CustomerTouchpointRepository::class;
    }

    protected function packageName(): string
    {
        return 'client_health';
    }

    protected function manifestEntityKey(): string
    {
        return 'customer_touchpoints';
    }

    protected function entityTag(): string
    {
        return 'customer_touchpoint';
    }

    protected function baseUrl(): string
    {
        return '/clienti/contatti';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Com\'e\' andata con il cliente?' : 'Contatto con il cliente';
    }

    protected function searchColumns(): array
    {
        return ['summary', 'next_step'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'contactDate', 'label' => 'Quando'],
            ['key' => 'summary', 'label' => 'Di cosa'],
            ['key' => 'mood', 'label' => "Com'era"],
        ];
    }

    public function name(): string
    {
        return 'edit_customer_touchpoint';
    }

    public function description(): string
    {
        return "Registra o apre un contatto con un cliente (telefonata, incontro, messaggio): com'era, di cosa si e' parlato, prossimo passo.";
    }

    public function requiredPermission(): ?string
    {
        return 'client_health.create';
    }
}

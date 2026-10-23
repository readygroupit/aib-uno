<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\CustomerContactRepository;

final class EditCustomerContactTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return CustomerContactRepository::class;
    }

    protected function packageName(): string
    {
        return 'customers';
    }

    protected function manifestEntityKey(): string
    {
        return 'customer_contacts';
    }

    protected function entityTag(): string
    {
        return 'customer_contact';
    }

    protected function baseUrl(): string
    {
        return '/clienti/referenti';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo referente' : 'Referente';
    }

    protected function searchColumns(): array
    {
        return ['full_name', 'email', 'phone'];
    }

    protected function phoneticColumns(): array
    {
        return ['full_name'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'fullName', 'label' => 'Nome'],
            ['key' => 'role', 'label' => 'Ruolo'],
            ['key' => 'email', 'label' => 'Email'],
        ];
    }

    public function name(): string
    {
        return 'edit_customer_contact';
    }

    public function description(): string
    {
        return "Apre un referente di un cliente (la persona da sentire: titolare, marketing, amministrazione), cercato per nome, email o telefono.";
    }

    public function requiredPermission(): ?string
    {
        return 'customers.edit';
    }
}

<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\CustomerRepository;

/**
 * Primo vero utilizzo di AbstractListEntityTool: solo i dati specifici
 * di 'customers', tutta la logica di lista/statistica/paginazione e'
 * ereditata.
 */
final class ListCustomersTool extends AbstractListEntityTool
{
    protected function repositoryClass(): string
    {
        return CustomerRepository::class;
    }

    protected function entityTag(): string
    {
        return 'customer';
    }

    protected function baseUrl(): string
    {
        return '/clienti';
    }

    protected function title(): string
    {
        return 'Clienti';
    }

    protected function statLabel(): string
    {
        return 'Totale clienti';
    }

    protected function createLabel(): ?string
    {
        return 'Nuovo cliente';
    }

    protected function createPermission(): ?string
    {
        return 'customers.create';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'firstName', 'label' => 'Nome'],
            ['key' => 'lastName', 'label' => 'Cognome'],
            ['key' => 'companyName', 'label' => 'Ragione sociale'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'phone', 'label' => 'Telefono'],
        ];
    }

    protected function actions(): array
    {
        return [
            ['label' => 'Modifica', 'href' => '/clienti/{id}', 'permission' => 'customers.edit'],
        ];
    }

    public function name(): string
    {
        return 'list_customers';
    }

    public function description(): string
    {
        return "Mostra l'elenco dei clienti con statistica totale.";
    }

    public function menuLabel(): ?string
    {
        return 'Clienti';
    }

    public function menuSection(): ?string
    {
        return 'crm';
    }

    public function requiredPermission(): ?string
    {
        return 'customers.view';
    }

    public function triggers(): array
    {
        return ['clienti', 'lista clienti', 'elenco clienti'];
    }
}

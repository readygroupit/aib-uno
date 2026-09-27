<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\CustomerRepository;

/**
 * Primo vero utilizzo di AbstractEditEntityTool: solo i dati specifici
 * di 'customers' (colonne di ricerca, testi, colonne della tabella di
 * scelta multipla) - ricerca/apertura/form/larghezze campi sono
 * ereditati.
 */
final class EditCustomerTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return CustomerRepository::class;
    }

    protected function packageName(): string
    {
        return 'customers';
    }

    protected function entityTag(): string
    {
        return 'customer';
    }

    protected function baseUrl(): string
    {
        return '/clienti';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo cliente' : 'Modifica cliente';
    }

    protected function searchColumns(): array
    {
        return ['first_name', 'last_name', 'company_name', 'email', 'phone', 'vat_number'];
    }

    protected function phoneticColumns(): array
    {
        return ['first_name', 'last_name', 'company_name'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'firstName', 'label' => 'Nome'],
            ['key' => 'lastName', 'label' => 'Cognome'],
            ['key' => 'companyName', 'label' => 'Ragione sociale'],
            ['key' => 'email', 'label' => 'Email'],
        ];
    }

    public function name(): string
    {
        return 'edit_customer';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di un cliente, cercato per id o per nome/cognome/ragione sociale/email/telefono/partita IVA. "
            . "Usalo quando l'utente chiede di aprire, vedere il dettaglio o modificare uno specifico cliente "
            . "(es. 'modifica il cliente Rossi', 'apri il cliente con partita iva ...').";
    }

    public function requiredPermission(): ?string
    {
        return 'customers.edit';
    }
}

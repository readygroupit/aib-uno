<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\CompanyProfileRepository;

/** I dati di chi emette le fatture (una riga sola). */
final class EditCompanyProfileTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return CompanyProfileRepository::class;
    }

    protected function packageName(): string
    {
        return 'invoices';
    }

    protected function manifestEntityKey(): string
    {
        return 'company_profiles';
    }

    protected function entityTag(): string
    {
        return 'company_profile';
    }

    protected function baseUrl(): string
    {
        return '/fatture/dati-azienda';
    }

    protected function formTitle(bool $isNew): string
    {
        return 'Dati dell\'azienda per le fatture';
    }

    protected function searchColumns(): array
    {
        return ['company_name'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'companyName', 'label' => 'Ragione sociale']];
    }

    public function name(): string
    {
        return 'edit_company_profile';
    }

    public function description(): string
    {
        return "Apre i dati dell'azienda che emette le fatture (ragione sociale, partita IVA, sede, regime fiscale, IBAN).";
    }

    public function requiredPermission(): ?string
    {
        return 'invoices.edit';
    }
}

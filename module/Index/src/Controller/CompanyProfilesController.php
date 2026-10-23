<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditCompanyProfileTool;
use App\Prompt\Tool\ListInvoicesTool;
use App\Repository\CompanyProfileRepository;

/**
 * I dati dell'azienda per le fatture: una riga sola, quindi
 * /fatture/dati-azienda apre direttamente quella (o il form vuoto).
 */
final class CompanyProfilesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return CompanyProfileRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditCompanyProfileTool::class;
    }

    protected function listToolClass(): string
    {
        return ListInvoicesTool::class;
    }

    protected function packageName(): string
    {
        return 'invoices';
    }

    protected function manifestEntityKey(): string
    {
        return 'company_profiles';
    }

    protected function listPageTitle(): string
    {
        return 'Dati azienda';
    }

    protected function newPageTitle(): string
    {
        return 'Dati azienda';
    }

    protected function editPageTitle(): string
    {
        return 'Dati azienda';
    }

    protected function defaultsOnCreate(): array
    {
        return ['country' => 'IT', 'tax_regime' => 'RF01', 'default_tax_rate' => '22'];
    }

    protected function createdMessage(): string
    {
        return 'Dati dell\'azienda salvati: finiranno in ogni fattura.';
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return 'invoices.edit';
    }

    public function indexAction(): ?string
    {
        $current = $this->container->get(CompanyProfileRepository::class)->current();
        $this->redirect($current !== null ? '/fatture/dati-azienda/' . $current->id : '/fatture/dati-azienda/nuovo');

        return null;
    }
}

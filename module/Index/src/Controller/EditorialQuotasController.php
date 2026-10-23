<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditEditorialQuotaTool;
use App\Prompt\Tool\ListEditorialQuotasTool;
use App\Repository\EditorialQuotaRepository;

final class EditorialQuotasController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return EditorialQuotaRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditEditorialQuotaTool::class;
    }

    protected function listToolClass(): string
    {
        return ListEditorialQuotasTool::class;
    }

    protected function packageName(): string
    {
        return 'editorial';
    }

    protected function manifestEntityKey(): string
    {
        return 'editorial_quotas';
    }

    protected function listPageTitle(): string
    {
        return 'Contratti social';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo contratto social';
    }

    protected function editPageTitle(): string
    {
        return 'Contratto social';
    }

    protected function defaultsOnCreate(): array
    {
        return ['channel' => 'Instagram', 'per_week' => '3'];
    }

    protected function createdMessage(): string
    {
        return 'Contratto salvato: il piano editoriale ora segnala cosa manca.';
    }
}

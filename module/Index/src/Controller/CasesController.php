<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditCaseTool;
use App\Prompt\Tool\ListCasesTool;
use App\Repository\CaseFileRepository;

final class CasesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return CaseFileRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditCaseTool::class;
    }

    protected function listToolClass(): string
    {
        return ListCasesTool::class;
    }

    protected function packageName(): string
    {
        return 'cases';
    }

    protected function listPageTitle(): string
    {
        return 'Pratiche';
    }

    protected function newPageTitle(): string
    {
        return 'Nuova pratica';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica pratica';
    }

    protected function defaultsOnCreate(): array
    {
        return ['stage' => 'non avviata'];
    }

    protected function createdMessage(): string
    {
        return 'Pratica creata.';
    }
}

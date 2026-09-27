<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditInterventionTool;
use App\Prompt\Tool\ListInterventionsTool;
use App\Repository\InterventionRepository;

final class InterventionsController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return InterventionRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditInterventionTool::class;
    }

    protected function listToolClass(): string
    {
        return ListInterventionsTool::class;
    }

    protected function packageName(): string
    {
        return 'interventions';
    }

    protected function listPageTitle(): string
    {
        return 'Interventi';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo intervento';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica intervento';
    }

    protected function defaultsOnCreate(): array
    {
        return ['stage' => 'da assegnare'];
    }

    protected function createdMessage(): string
    {
        return 'Intervento creato.';
    }
}

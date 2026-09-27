<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditServiceTool;
use App\Prompt\Tool\ListServicesTool;
use App\Repository\ServiceRepository;

final class ServicesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return ServiceRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditServiceTool::class;
    }

    protected function listToolClass(): string
    {
        return ListServicesTool::class;
    }

    protected function packageName(): string
    {
        return 'services';
    }

    protected function listPageTitle(): string
    {
        return 'Servizi';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo servizio';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica servizio';
    }

    protected function defaultsOnCreate(): array
    {
        return ['stage' => 'attivo'];
    }

    protected function createdMessage(): string
    {
        return 'Servizio creato.';
    }
}

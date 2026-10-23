<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditEditorialStrategyTool;
use App\Prompt\Tool\ShowEditorialTool;
use App\Repository\EditorialStrategyRepository;

/** Strategie social dei clienti: si aprono dalla riga del cliente nel piano editoriale. */
final class EditorialStrategiesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return EditorialStrategyRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditEditorialStrategyTool::class;
    }

    protected function listToolClass(): string
    {
        return ShowEditorialTool::class;
    }

    protected function packageName(): string
    {
        return 'editorial';
    }

    protected function manifestEntityKey(): string
    {
        return 'editorial_strategies';
    }

    protected function listPageTitle(): string
    {
        return 'Piano editoriale';
    }

    protected function newPageTitle(): string
    {
        return 'Strategia social';
    }

    protected function editPageTitle(): string
    {
        return 'Strategia social';
    }

    protected function createdMessage(): string
    {
        return 'Strategia salvata: ora "Proponi con l\'IA" nel piano editoriale parte da qui.';
    }
}

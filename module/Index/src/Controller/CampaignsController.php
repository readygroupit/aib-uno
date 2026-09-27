<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditCampaignTool;
use App\Prompt\Tool\ListCampaignsTool;
use App\Repository\CampaignRepository;

final class CampaignsController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return CampaignRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditCampaignTool::class;
    }

    protected function listToolClass(): string
    {
        return ListCampaignsTool::class;
    }

    protected function packageName(): string
    {
        return 'campaigns';
    }

    protected function manifestEntityKey(): string
    {
        return 'campaigns';
    }

    protected function listPageTitle(): string
    {
        return 'Campagne marketing';
    }

    protected function newPageTitle(): string
    {
        return 'Nuova campagna';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica campagna';
    }

    protected function defaultsOnCreate(): array
    {
        return ['stage' => 'bozza', 'sent_count' => '0'];
    }

    protected function createdMessage(): string
    {
        return 'Campagna creata.';
    }
}

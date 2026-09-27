<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\CampaignRepository;

final class ListCampaignsTool extends AbstractListEntityTool
{
    protected function repositoryClass(): string
    {
        return CampaignRepository::class;
    }

    protected function entityTag(): string
    {
        return 'campaign';
    }

    protected function baseUrl(): string
    {
        return '/campagne';
    }

    protected function title(): string
    {
        return 'Campagne marketing';
    }

    protected function statLabel(): string
    {
        return 'Totale campagne';
    }

    protected function createLabel(): ?string
    {
        return 'Nuova campagna';
    }

    protected function createPermission(): ?string
    {
        return 'campaigns.create';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome'],
            ['key' => 'stage', 'label' => 'Stato'],
            ['key' => 'scheduledAt', 'label' => 'Programmata per'],
            ['key' => 'sentCount', 'label' => 'Inviate'],
        ];
    }

    protected function actions(): array
    {
        return [
            ['label' => 'Modifica', 'href' => '/campagne/{id}', 'permission' => 'campaigns.edit'],
        ];
    }

    public function name(): string
    {
        return 'list_campaigns';
    }

    public function description(): string
    {
        return "Mostra l'elenco delle campagne marketing con statistica totale.";
    }

    public function menuLabel(): ?string
    {
        return 'Campagne';
    }

    public function menuSection(): ?string
    {
        return 'crm';
    }

    public function requiredPermission(): ?string
    {
        return 'campaigns.view';
    }

    public function triggers(): array
    {
        return ['campagne', 'lista campagne', 'elenco campagne', 'campagne marketing'];
    }
}

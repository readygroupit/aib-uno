<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\CampaignRepository;

final class EditCampaignTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return CampaignRepository::class;
    }

    protected function packageName(): string
    {
        return 'campaigns';
    }

    protected function manifestEntityKey(): string
    {
        return 'campaigns';
    }

    protected function entityTag(): string
    {
        return 'campaign';
    }

    protected function baseUrl(): string
    {
        return '/campagne';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuova campagna' : 'Modifica campagna';
    }

    protected function searchColumns(): array
    {
        return ['name', 'subject'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome'],
            ['key' => 'stage', 'label' => 'Stato'],
        ];
    }

    public function name(): string
    {
        return 'edit_campaign';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di una campagna marketing, cercata per id o per nome/oggetto. "
            . "Usalo quando l'utente chiede di aprire o modificare una specifica campagna.";
    }

    public function requiredPermission(): ?string
    {
        return 'campaigns.edit';
    }
}

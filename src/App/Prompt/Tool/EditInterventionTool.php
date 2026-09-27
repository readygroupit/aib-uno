<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\InterventionRepository;

final class EditInterventionTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return InterventionRepository::class;
    }

    protected function packageName(): string
    {
        return 'interventions';
    }

    protected function entityTag(): string
    {
        return 'intervention';
    }

    protected function baseUrl(): string
    {
        return '/interventi';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo intervento' : 'Modifica intervento';
    }

    protected function searchColumns(): array
    {
        return ['title', 'notes'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titolo'],
            ['key' => 'scheduledAt', 'label' => 'Data'],
            ['key' => 'stage', 'label' => 'Stato'],
        ];
    }

    public function name(): string
    {
        return 'edit_intervention';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di un intervento tecnico, cercato per id o per titolo/note. "
            . "Usalo quando l'utente chiede di aprire o modificare uno specifico intervento.";
    }

    public function requiredPermission(): ?string
    {
        return 'interventions.edit';
    }
}

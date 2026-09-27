<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\TaskRepository;

final class EditTaskTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return TaskRepository::class;
    }

    protected function packageName(): string
    {
        return 'tasks';
    }

    protected function entityTag(): string
    {
        return 'task';
    }

    protected function baseUrl(): string
    {
        return '/attivita';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuova attivita' : 'Modifica attivita';
    }

    protected function searchColumns(): array
    {
        return ['title', 'description'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titolo'],
            ['key' => 'dueAt', 'label' => 'Scadenza'],
            ['key' => 'stage', 'label' => 'Stato'],
        ];
    }

    public function name(): string
    {
        return 'edit_task';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di un'attivita/promemoria, cercata per id o per titolo/descrizione. "
            . "Usalo quando l'utente chiede di aprire o modificare una specifica attivita (es. 'apri l'attivita richiamo Rossi').";
    }

    public function requiredPermission(): ?string
    {
        return 'tasks.edit';
    }
}

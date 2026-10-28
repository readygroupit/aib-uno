<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\WorkEntryRepository;

final class EditWorkEntryTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return WorkEntryRepository::class;
    }

    protected function packageName(): string
    {
        return 'worklog';
    }

    protected function manifestEntityKey(): string
    {
        return 'work_entries';
    }

    protected function entityTag(): string
    {
        return 'work_entry';
    }

    protected function baseUrl(): string
    {
        return '/registro/voci';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Aggiungi al registro' : 'Voce del registro';
    }

    /**
     * 'source' (check-in o a mano) lo scrive il sistema: nel form era un
     * menu "Come: check-in / manuale" che non si capiva a cosa servisse.
     */
    protected function hiddenFields(): array
    {
        return ['task_id', 'user_id', 'source'];
    }

    protected function searchColumns(): array
    {
        return ['title'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'title', 'label' => 'Cosa'], ['key' => 'workDate', 'label' => 'Giorno'], ['key' => 'minutes', 'label' => 'Minuti']];
    }

    public function name(): string
    {
        return 'edit_work_entry';
    }

    public function description(): string
    {
        return "Apre una voce del registro attivita'.";
    }

    public function requiredPermission(): ?string
    {
        return 'worklog.view';
    }
}

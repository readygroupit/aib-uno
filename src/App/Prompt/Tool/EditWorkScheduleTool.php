<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Model\AbstractModel;
use App\Repository\WorkScheduleRepository;

final class EditWorkScheduleTool extends AbstractEditEntityTool
{
    /** Le persone gia' scelte nel form nuovo ("2,3"), quando torna indietro con un errore. */
    private ?string $selectedUsers = null;

    public function selectUsers(string $userIds): void
    {
        $this->selectedUsers = $userIds;
    }

    /**
     * Nel form nuovo si scelgono piu' persone insieme (stesso orario per
     * tutte, un orario per ognuna); una volta creato, l'orario e' di una.
     */
    protected function fieldsFromManifest(AbstractModel $model, array $fieldErrors = []): array
    {
        $fields = parent::fieldsFromManifest($model, $fieldErrors);
        if ($model->id !== null) {
            return $fields;
        }

        foreach ($fields as &$field) {
            if ($field['key'] === 'user_id') {
                $field['label'] = 'Persone';
                $field['inputType'] = 'checkboxes';
                $field['choices'] = $this->choices('staff');
                $field['value'] = $this->selectedUsers ?? ($field['value'] !== null ? (string) $field['value'] : '');
                $field['width'] = 'full';
                $field['help'] = "Tutte quelle che lavorano con questo orario: se ne crea uno per ognuna.";
            }
        }
        unset($field);

        return $fields;
    }
    protected function repositoryClass(): string
    {
        return WorkScheduleRepository::class;
    }

    protected function packageName(): string
    {
        return 'planner';
    }

    protected function manifestEntityKey(): string
    {
        return 'work_schedules';
    }

    protected function entityTag(): string
    {
        return 'work_schedule';
    }

    protected function baseUrl(): string
    {
        return '/orari';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo orario di lavoro' : 'Orario di lavoro';
    }

    protected function searchColumns(): array
    {
        return ['start_time'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'userId', 'label' => 'Persona'], ['key' => 'startTime', 'label' => 'Inizio']];
    }

    public function name(): string
    {
        return 'edit_work_schedule';
    }

    public function description(): string
    {
        return "Apre l'orario di lavoro di una persona.";
    }

    public function requiredPermission(): ?string
    {
        return 'planner.team';
    }
}

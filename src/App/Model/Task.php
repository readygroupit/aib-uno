<?php

declare(strict_types=1);

namespace App\Model;

final class Task extends AbstractModel
{
    public ?string $title = null;
    public ?string $description = null;
    public ?string $dueAt = null;
    public ?string $stage = null;
    public ?int $assignedUserId = null;
    public ?string $completedAt = null;
    public ?string $entityType = null;
    public ?int $entityId = null;
    public ?string $plannedDate = null;
    public ?string $priority = null;
    public ?int $estimatedMinutes = null;
    public ?int $actualMinutes = null;
    public ?int $position = null;
    public ?int $waitsForClient = null;
    public ?string $checklist = null;
    public ?string $recurrence = null;
    public ?int $sourceStepId = null;

    public function isOpen(): bool
    {
        return in_array($this->stage, ['da fare', 'in corso'], true) || $this->stage === null;
    }

    /** Il giorno a cui appartiene: quello previsto, altrimenti quello della scadenza. */
    public function day(): ?string
    {
        return $this->plannedDate ?? ($this->dueAt !== null ? substr($this->dueAt, 0, 10) : null);
    }

    /** @return array{done:int,total:int} */
    public function checklistProgress(): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", (string) $this->checklist)), static fn (string $l) => $l !== ''));
        $done = count(array_filter($lines, static fn (string $l) => str_starts_with(strtolower($l), '[x]')));

        return ['done' => $done, 'total' => count($lines)];
    }
}

<?php

declare(strict_types=1);

namespace App\Model;

final class ClientRequest extends AbstractModel
{
    public ?int $customerId = null;
    public ?string $summary = null;
    public ?string $details = null;
    public ?string $kind = null;
    public ?string $channel = null;
    public ?string $receivedAt = null;
    public ?int $receivedByUserId = null;
    public ?int $assignedUserId = null;
    public ?string $stage = null;
    public ?string $priority = null;
    public ?string $dueDate = null;
    public ?int $estimatedMinutes = null;
    public ?int $isExtra = null;
    public ?string $extraAmount = null;
    public ?int $installmentId = null;
    public ?int $taskId = null;
    public ?string $resolution = null;
    public ?string $doneAt = null;

    public function isOpen(): bool
    {
        return in_array($this->stage, ['nuova', 'presa in carico'], true);
    }
}

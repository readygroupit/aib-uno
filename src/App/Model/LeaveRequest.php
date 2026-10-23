<?php

declare(strict_types=1);

namespace App\Model;

final class LeaveRequest extends AbstractModel
{
    public ?int $userId = null;
    public ?string $kind = null;
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?string $startTime = null;
    public ?string $endTime = null;
    public ?string $stage = null;
    public ?string $note = null;
    public ?int $decidedBy = null;
    public ?string $decidedAt = null;
    public ?string $reply = null;

    public function lastDay(): string
    {
        return $this->endDate ?: (string) $this->startDate;
    }

    /** Solo qualche ora (permesso con orario), non la giornata intera. */
    public function isPartial(): bool
    {
        return $this->startTime !== null && $this->endTime !== null && $this->startTime < $this->endTime;
    }
}

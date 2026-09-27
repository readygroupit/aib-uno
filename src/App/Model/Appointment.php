<?php

declare(strict_types=1);

namespace App\Model;

final class Appointment extends AbstractModel
{
    public ?string $title = null;
    public ?string $startAt = null;
    public ?string $endAt = null;
    public ?string $location = null;
    public ?string $stage = null;
    public ?int $assignedUserId = null;
    public ?int $leadId = null;
    public ?int $customerId = null;
    public ?int $reminderMinutesBefore = null;
    public ?string $notes = null;
}

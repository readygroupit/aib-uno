<?php

declare(strict_types=1);

namespace App\Model;

final class WorkOrderMilestone extends AbstractModel
{
    public ?int $workOrderId = null;
    public ?int $milestoneId = null;
    public ?int $position = null;
    public ?string $label = null;
    public ?string $scheduledAt = null;
    public ?int $sendClientEmail = null;
    public ?string $clientEmailTo = null;
    public ?string $clientEmailSubject = null;
    public ?string $clientEmailBody = null;
    public ?string $clientEmailSentAt = null;
    public ?int $appointmentId = null;
}

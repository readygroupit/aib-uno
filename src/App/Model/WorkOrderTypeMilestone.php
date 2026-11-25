<?php

declare(strict_types=1);

namespace App\Model;

final class WorkOrderTypeMilestone extends AbstractModel
{
    public ?int $workOrderTypeId = null;
    public ?int $position = null;
    public ?string $label = null;
    public ?int $setByRoleId = null;
    public ?int $afterStepId = null;
    public ?int $createsAppointment = null;
    public ?string $clientEmailSubject = null;
    public ?string $clientEmailBody = null;
}

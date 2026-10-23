<?php

declare(strict_types=1);

namespace App\Model;

final class WorkOrderTypeStep extends AbstractModel
{
    public ?int $workOrderTypeId = null;
    public ?int $position = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?int $offsetDays = null;
    public ?int $estimatedMinutes = null;
    public ?int $waitsForClient = null;
    public ?string $checklist = null;
}

<?php

declare(strict_types=1);

namespace App\Model;

final class WorkOrderDeliverable extends AbstractModel
{
    public ?int $workOrderId = null;
    public ?int $position = null;
    public ?string $label = null;
    public ?string $description = null;
    public ?int $isDone = null;
    public ?string $doneAt = null;
}

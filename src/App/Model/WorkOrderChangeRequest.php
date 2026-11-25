<?php

declare(strict_types=1);

namespace App\Model;

final class WorkOrderChangeRequest extends AbstractModel
{
    public ?int $workOrderId = null;
    public ?string $requestedAt = null;
    public ?string $description = null;
    public ?int $assignedUserId = null;
    public ?int $isExtra = null;
    public ?string $stage = null;
    public ?int $quoteId = null;
}

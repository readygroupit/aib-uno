<?php

declare(strict_types=1);

namespace App\Model;

final class WorkOrderMember extends AbstractModel
{
    public ?int $workOrderId = null;
    public ?int $userId = null;
    public ?int $roleId = null;
    public ?string $notes = null;
}

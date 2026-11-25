<?php

declare(strict_types=1);

namespace App\Model;

final class WorkOrderTypeRole extends AbstractModel
{
    public ?int $workOrderTypeId = null;
    public ?int $position = null;
    public ?string $label = null;
    public ?string $description = null;
    public ?int $defaultUserId = null;
    public ?string $customerTeamRole = null;
    public ?int $isMultiple = null;
    public ?int $isRequired = null;
}

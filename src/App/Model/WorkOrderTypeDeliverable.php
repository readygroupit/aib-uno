<?php

declare(strict_types=1);

namespace App\Model;

final class WorkOrderTypeDeliverable extends AbstractModel
{
    public ?int $workOrderTypeId = null;
    public ?int $position = null;
    public ?string $label = null;
    public ?string $description = null;
    public ?int $alwaysIncluded = null;
}

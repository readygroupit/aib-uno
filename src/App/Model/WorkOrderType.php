<?php

declare(strict_types=1);

namespace App\Model;

final class WorkOrderType extends AbstractModel
{
    public ?string $name = null;
    public ?string $description = null;
    public ?int $defaultDays = null;
    public ?string $stage = null;
}

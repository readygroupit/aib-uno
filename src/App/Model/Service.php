<?php

declare(strict_types=1);

namespace App\Model;

final class Service extends AbstractModel
{
    public ?string $name = null;
    public ?string $category = null;
    public ?string $description = null;
    public ?string $stage = null;
    public ?string $price = null;
    public ?string $taxRatePercent = null;
    public ?int $durationMinutes = null;
    public ?string $billingType = null;
    public ?string $recurrence = null;
    public ?string $costPrice = null;
    public ?int $supplierCustomerId = null;
}

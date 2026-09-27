<?php

declare(strict_types=1);

namespace App\Model;

final class Intervention extends AbstractModel
{
    public ?string $title = null;
    public ?int $customerId = null;
    public ?string $scheduledAt = null;
    public ?string $stage = null;
    public ?string $operationType = null;
    public ?int $assignedUserId = null;
    public ?string $callOutFee = null;
    public ?string $laborCost = null;
    public ?string $total = null;
    public ?string $paymentMethod = null;
    public ?string $notes = null;
}

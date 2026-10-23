<?php

declare(strict_types=1);

namespace App\Model;

final class WorkOrder extends AbstractModel
{
    public ?int $customerId = null;
    public ?int $workOrderTypeId = null;
    public ?int $customerServiceId = null;
    public ?string $title = null;
    public ?string $stage = null;
    public ?string $priority = null;
    public ?int $ownerUserId = null;
    public ?int $supplierCustomerId = null;
    public ?string $startDate = null;
    public ?string $dueDate = null;
    public ?string $deliveredAt = null;
    public ?string $waitingReason = null;
    public ?string $waitingSince = null;
    public ?string $lastReminderAt = null;
    public ?string $lastActivityAt = null;
    public ?string $notes = null;

    public function isActive(): bool
    {
        return in_array($this->stage, ['da avviare', 'in corso', 'in attesa del cliente', 'in pausa'], true);
    }
}

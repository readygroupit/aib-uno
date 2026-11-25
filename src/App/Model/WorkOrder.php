<?php

declare(strict_types=1);

namespace App\Model;

final class WorkOrder extends AbstractModel
{
    /** Contatto e preventivo vengono prima del lavoro: il progetto c'e' gia', il cliente non ha ancora firmato. */
    public const ACTIVE_STAGES = ['contatto', 'preventivo', 'da avviare', 'in corso', 'in attesa del cliente', 'in pausa'];

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
    public ?int $quoteId = null;
    public ?string $sourceChannel = null;
    public ?string $lostReason = null;

    public function isCommercial(): bool
    {
        return in_array($this->stage, ['contatto', 'preventivo'], true);
    }

    public function isActive(): bool
    {
        return in_array($this->stage, self::ACTIVE_STAGES, true);
    }
}

<?php

declare(strict_types=1);

namespace App\Model;

final class Installment extends AbstractModel
{
    public ?int $customerId = null;
    public ?int $customerServiceId = null;
    public ?string $kind = null;
    public ?string $description = null;
    public ?string $dueDate = null;
    public ?string $amount = null;
    public ?string $stage = null;
    public ?string $paidAt = null;
    public ?string $paymentMethod = null;
    public ?string $periodStart = null;
    public ?string $periodEnd = null;
    public ?string $reminderSentAt = null;
    public ?string $notes = null;
    public ?string $paymentProvider = null;
    public ?string $paymentReference = null;
    public ?string $paymentLinkUrl = null;
    public ?int $invoiceId = null;
}

<?php

declare(strict_types=1);

namespace App\Model;

final class Refund extends AbstractModel
{
    public ?int $caseId = null;
    public ?string $amountClaimed = null;
    public ?string $amountAccepted = null;
    public ?string $paymentStatus = null;
    public ?string $paidAt = null;
    public ?string $invoiceReference = null;
    public ?string $notes = null;
}

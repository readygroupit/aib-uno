<?php

declare(strict_types=1);

namespace App\Model;

final class Expense extends AbstractModel
{
    public ?int $userId = null;
    public ?string $expenseDate = null;
    public ?string $kind = null;
    public ?string $amount = null;
    public ?string $description = null;
    public ?int $customerId = null;
    public ?int $workOrderId = null;
    public ?string $paidBy = null;
    public ?int $rebill = null;
    public ?string $receiptLink = null;
    public ?string $stage = null;
    public ?int $decidedBy = null;
    public ?string $decidedAt = null;
    public ?string $reimbursedAt = null;

    /** Pagata con soldi propri: va restituita. */
    public function toReimburse(): bool
    {
        return $this->paidBy === null || $this->paidBy === 'soldi miei';
    }
}

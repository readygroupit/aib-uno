<?php

declare(strict_types=1);

namespace App\Model;

final class CustomerService extends AbstractModel
{
    public ?int $customerId = null;
    public ?int $serviceId = null;
    public ?string $title = null;
    public ?string $stage = null;
    public ?string $billingType = null;
    public ?string $recurrence = null;
    public ?string $price = null;
    public ?string $costPrice = null;
    public ?string $startDate = null;
    public ?string $renewalDate = null;
    public ?string $endDate = null;
    public ?int $autoRenew = null;
    public ?int $noticeDays = null;
    public ?string $paymentPlan = null;
    public ?string $depositPercent = null;
    public ?string $balanceDate = null;
    public ?int $installmentsCount = null;
    public ?string $paymentMethod = null;
    public ?int $supplierCustomerId = null;
    public ?string $notes = null;
    public ?string $results = null;
    public ?string $resultsNote = null;
    public ?string $resultsUpdatedAt = null;
    public ?string $reference = null;

    public function isRecurring(): bool
    {
        return $this->billingType === 'ricorrente';
    }
}

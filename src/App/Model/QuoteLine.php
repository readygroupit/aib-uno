<?php

declare(strict_types=1);

namespace App\Model;

final class QuoteLine extends AbstractModel
{
    public ?int $quoteId = null;
    public ?int $serviceId = null;
    public ?string $description = null;
    public ?string $details = null;
    public ?string $quantity = null;
    public ?string $unitPrice = null;
    public ?string $billingType = null;
    public ?string $recurrence = null;
    public ?string $paymentPlan = null;
    public ?string $depositPercent = null;
    public ?int $installmentsCount = null;
    public ?string $startDate = null;
    public ?int $position = null;
    public ?int $customerServiceId = null;

    public function isRecurring(): bool
    {
        return $this->billingType === 'ricorrente';
    }

    /** Importo della riga (quantita' x prezzo), senza sconto. */
    public function amount(): float
    {
        return round((float) ($this->quantity ?? 1) * (float) $this->unitPrice, 2);
    }
}

<?php

declare(strict_types=1);

namespace App\Model;

final class Invoice extends AbstractModel
{
    public ?string $invoiceNumber = null;
    public ?int $numberYear = null;
    public ?int $numberSeq = null;
    public ?int $customerId = null;
    public ?string $issueDate = null;
    public ?string $dueDate = null;
    public ?string $stage = null;
    public ?string $description = null;
    public ?string $subtotal = null;
    public ?string $taxRatePercent = null;
    public ?string $taxAmount = null;
    public ?string $total = null;
    public ?string $paymentMethod = null;
    public ?int $customerServiceId = null;
    public ?int $installmentId = null;
    public ?string $sdiStatus = null;
    public ?string $sdiProvider = null;
    public ?string $sdiReference = null;
    public ?string $sentAt = null;
    public ?string $documentType = null;

    public function isProForma(): bool
    {
        return $this->documentType === 'pro forma';
    }
}

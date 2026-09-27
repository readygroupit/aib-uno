<?php

declare(strict_types=1);

namespace App\Model;

final class Invoice extends AbstractModel
{
    public ?string $invoiceNumber = null;
    public ?int $customerId = null;
    public ?string $issueDate = null;
    public ?string $dueDate = null;
    public ?string $stage = null;
    public ?string $description = null;
    public ?string $subtotal = null;
    public ?string $taxAmount = null;
    public ?string $total = null;
    public ?string $paymentMethod = null;
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Invoice;

final class InvoiceRepository extends AbstractRepository
{
    protected string $table = 'invoices';
    protected string $modelClass = Invoice::class;
}

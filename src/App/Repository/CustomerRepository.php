<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Customer;

final class CustomerRepository extends AbstractRepository
{
    protected string $table = 'customers';
    protected string $modelClass = Customer::class;
}

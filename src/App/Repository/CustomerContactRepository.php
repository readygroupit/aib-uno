<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\CustomerContact;

final class CustomerContactRepository extends AbstractRepository
{
    protected string $table = 'customer_contacts';
    protected string $modelClass = CustomerContact::class;

    /** @return CustomerContact[] chi decide per primo */
    public function findByCustomer(int $customerId): array
    {
        return $this->findAll(['customer_id' => $customerId], 'is_decision_maker DESC, full_name');
    }
}

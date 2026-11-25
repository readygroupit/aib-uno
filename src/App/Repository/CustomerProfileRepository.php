<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\CustomerProfile;

final class CustomerProfileRepository extends AbstractRepository
{
    protected string $table = 'customer_profiles';
    protected string $modelClass = CustomerProfile::class;

    public function findByCustomer(int $customerId): ?CustomerProfile
    {
        $rows = $this->findAll(['customer_id' => $customerId], 'id');

        return $rows[0] ?? null;
    }
}

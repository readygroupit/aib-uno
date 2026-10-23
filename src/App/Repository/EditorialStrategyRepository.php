<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\EditorialStrategy;

final class EditorialStrategyRepository extends AbstractRepository
{
    protected string $table = 'editorial_strategies';
    protected string $modelClass = EditorialStrategy::class;

    public function findByCustomer(int $customerId): ?EditorialStrategy
    {
        $found = $this->findAll(['customer_id' => $customerId], 'id DESC');

        return $found[0] ?? null;
    }

    /** @return array<int, int> cliente => id della strategia */
    public function idsByCustomer(): array
    {
        $result = [];
        foreach ($this->findAll([], 'id') as $strategy) {
            $result[(int) $strategy->customerId] = (int) $strategy->id;
        }

        return $result;
    }
}

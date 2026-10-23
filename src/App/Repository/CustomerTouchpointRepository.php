<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\CustomerTouchpoint;

final class CustomerTouchpointRepository extends AbstractRepository
{
    protected string $table = 'customer_touchpoints';
    protected string $modelClass = CustomerTouchpoint::class;

    /** @return CustomerTouchpoint[] di un cliente, dal piu' recente */
    public function findByCustomer(int $customerId, int $limit = 20): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM customer_touchpoints WHERE status != 0 AND customer_id = :c ORDER BY contact_date DESC, id DESC LIMIT " . max(1, $limit),
            ['c' => $customerId]
        );

        return array_map(static fn (array $row) => CustomerTouchpoint::fromArray($row), $rows);
    }

    /** @return CustomerTouchpoint[] gli ultimi contatti con tutti i clienti */
    public function findRecent(int $limit): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM customer_touchpoints WHERE status != 0 ORDER BY contact_date DESC, id DESC LIMIT ' . max(1, $limit)
        );

        return array_map(static fn (array $row) => CustomerTouchpoint::fromArray($row), $rows);
    }

    /** @return array<int, CustomerTouchpoint> cliente => ultimo contatto */
    public function lastPerCustomer(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT t.* FROM customer_touchpoints t
             JOIN (SELECT customer_id, MAX(contact_date) AS d FROM customer_touchpoints WHERE status != 0 GROUP BY customer_id) last
               ON last.customer_id = t.customer_id AND last.d = t.contact_date
             WHERE t.status != 0
             ORDER BY t.id'
        );
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['customer_id']] = CustomerTouchpoint::fromArray($row);
        }

        return $result;
    }
}

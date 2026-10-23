<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\CustomerService;

final class CustomerServiceRepository extends AbstractRepository
{
    protected string $table = 'customer_services';
    protected string $modelClass = CustomerService::class;

    /** @return CustomerService[] attivi prima, poi i piu' recenti */
    public function findByCustomer(int $customerId): array
    {
        return $this->findAll(
            ['customer_id' => $customerId],
            "FIELD(stage, 'attivo', 'sospeso', 'disdetto', 'concluso'), start_date DESC"
        );
    }

    /** @return CustomerService[] */
    public function findActive(): array
    {
        return $this->findAll(['stage' => 'attivo'], 'customer_id, start_date');
    }

    /**
     * Servizi ricorrenti attivi con il rinnovo entro $until (anche quelli
     * gia' passati e non ancora rinnovati), il piu' vicino prima.
     *
     * @return CustomerService[]
     */
    public function findRenewalsUntil(string $until): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM customer_services
             WHERE status != 0 AND stage = 'attivo' AND billing_type = 'ricorrente'
               AND renewal_date IS NOT NULL AND renewal_date <= :until
             ORDER BY renewal_date",
            ['until' => $until]
        );

        return array_map(static fn (array $row) => CustomerService::fromArray($row), $rows);
    }

    /** @return CustomerService[] ricorrenti attivi con rinnovo automatico e data di rinnovo arrivata */
    public function findDueForAutoRenew(string $today): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM customer_services
             WHERE status != 0 AND stage = 'attivo' AND billing_type = 'ricorrente'
               AND auto_renew = 1 AND renewal_date IS NOT NULL AND renewal_date <= :today
             ORDER BY renewal_date",
            ['today' => $today]
        );

        return array_map(static fn (array $row) => CustomerService::fromArray($row), $rows);
    }

    /** @return array<int,string> id => descrizione, per mostrare il servizio accanto a una rata */
    public function titlesByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll("SELECT id, title FROM customer_services WHERE id IN ({$placeholders})", $ids);

        return array_column($rows, 'title', 'id');
    }
}

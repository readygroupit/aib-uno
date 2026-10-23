<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\ClientRequest;

final class ClientRequestRepository extends AbstractRepository
{
    protected string $table = 'client_requests';
    protected string $modelClass = ClientRequest::class;

    /** @return ClientRequest[] aperte e chiuse da $since, le piu' urgenti prima */
    public function findForBoard(string $since): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM client_requests
             WHERE status != 0 AND (stage IN ('nuova', 'presa in carico') OR done_at >= :since OR (stage = 'fatta' AND is_extra = 1 AND installment_id IS NULL))
             ORDER BY FIELD(priority, 'alta', 'normale', 'bassa'), COALESCE(due_date, DATE(received_at)), id",
            ['since' => $since]
        );

        return array_map(static fn (array $row) => ClientRequest::fromArray($row), $rows);
    }

    /** @return ClientRequest[] aperte di un cliente */
    public function findOpenForCustomer(int $customerId): array
    {
        return array_values(array_filter(
            $this->findAll(['customer_id' => $customerId], 'received_at DESC'),
            static fn (ClientRequest $r) => $r->isOpen()
        ));
    }

    /** @return array<int,int> cliente => richieste negli ultimi $days giorni */
    public function countPerCustomerSince(string $since): array
    {
        $result = [];
        foreach ($this->db->fetchAll('SELECT customer_id, COUNT(*) AS n FROM client_requests WHERE status != 0 AND received_at >= :s GROUP BY customer_id', ['s' => $since]) as $row) {
            $result[(int) $row['customer_id']] = (int) $row['n'];
        }

        return $result;
    }
}

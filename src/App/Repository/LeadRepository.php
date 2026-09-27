<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Lead;

final class LeadRepository extends AbstractRepository
{
    protected string $table = 'leads';
    protected string $modelClass = Lead::class;

    /**
     * Stessa logica di UserRepository::searchByName(): ricerca libera per
     * nome/cognome/email/telefono che CONTENGONO il termine, con
     * fallback fonetico via SOUNDEX se la ricerca esatta non trova nulla
     * (utile soprattutto per la dettatura vocale - vedi li' per i
     * dettagli). Qui il fallback fonetico resta su nome/cognome soltanto,
     * mai su email/telefono: SOUNDEX ha senso solo per parole lette a
     * voce, non per stringhe come indirizzi o numeri.
     *
     * @return Lead[]
     */
    public function searchByName(string $query, int $limit = 10): array
    {
        $exact = $this->searchByNameExact($query, $limit);

        return $exact !== [] ? $exact : $this->searchByNamePhonetic($query, $limit);
    }

    private function searchByNameExact(string $query, int $limit): array
    {
        $sql = 'SELECT * FROM leads WHERE status != 0 AND ('
            . 'first_name LIKE :q1 OR last_name LIKE :q2 OR company_name LIKE :q3 '
            . 'OR email LIKE :q4 OR phone LIKE :q5'
            . ') ORDER BY id DESC LIMIT ' . max(1, $limit);
        $like = '%' . $query . '%';

        return array_map(
            static fn (array $row) => Lead::fromArray($row),
            $this->db->fetchAll($sql, ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like])
        );
    }

    private function searchByNamePhonetic(string $query, int $limit): array
    {
        $words = array_slice(preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY), 0, 3);
        if ($words === []) {
            return [];
        }

        $conditions = [];
        $params = [];
        foreach ($words as $i => $word) {
            foreach (['first_name', 'last_name'] as $j => $field) {
                $key = "w{$i}_{$j}";
                $conditions[] = "SOUNDEX({$field}) = SOUNDEX(:{$key})";
                $params[$key] = $word;
            }
        }

        $sql = 'SELECT * FROM leads WHERE status != 0 AND (' . implode(' OR ', $conditions) . ')'
            . ' ORDER BY id DESC LIMIT ' . max(1, $limit);

        return array_map(
            static fn (array $row) => Lead::fromArray($row),
            $this->db->fetchAll($sql, $params)
        );
    }
}

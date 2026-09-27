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

    /**
     * Coppie di contatti attivi non ancora collegati che condividono
     * email, telefono o stesso volo+data - "assistito" per costruzione
     * (brief Assilevi: mai un merge/eliminazione automatica, solo un
     * suggerimento da confermare a mano, vedi LeadDuplicatesController).
     * b.id sempre il piu' recente dei due (a.id < b.id): e' su b che poi
     * si scrive related_lead_id -> a.id quando l'operatore conferma.
     *
     * @return list<array{aId:int,aLabel:string,bId:int,bLabel:string,reason:string}>
     */
    public function findDuplicateCandidates(int $limit = 20): array
    {
        $sql = "SELECT a.id AS a_id, a.first_name AS a_first_name, a.last_name AS a_last_name, a.company_name AS a_company_name,
                       b.id AS b_id, b.first_name AS b_first_name, b.last_name AS b_last_name, b.company_name AS b_company_name,
                       CASE
                           WHEN a.email IS NOT NULL AND a.email != '' AND a.email = b.email THEN 'stessa email'
                           WHEN a.phone IS NOT NULL AND a.phone != '' AND a.phone = b.phone THEN 'stesso telefono'
                           ELSE 'stesso volo e data'
                       END AS reason
                FROM leads a
                JOIN leads b ON a.id < b.id
                WHERE a.status != 0 AND b.status != 0 AND b.related_lead_id IS NULL
                  AND (
                    (a.email IS NOT NULL AND a.email != '' AND a.email = b.email)
                    OR (a.phone IS NOT NULL AND a.phone != '' AND a.phone = b.phone)
                    OR (a.flight_route IS NOT NULL AND a.flight_route != '' AND a.flight_date IS NOT NULL
                        AND a.flight_route = b.flight_route AND a.flight_date = b.flight_date)
                  )
                ORDER BY b.id DESC
                LIMIT " . max(1, $limit);

        return array_map(
            fn (array $row) => [
                'aId' => (int) $row['a_id'],
                'aLabel' => $this->displayLabel($row['a_first_name'], $row['a_last_name'], $row['a_company_name']),
                'bId' => (int) $row['b_id'],
                'bLabel' => $this->displayLabel($row['b_first_name'], $row['b_last_name'], $row['b_company_name']),
                'reason' => $row['reason'],
            ],
            $this->db->fetchAll($sql)
        );
    }

    /** Il probabile duplicato piu' vecchio di $leadId, se ce n'e' uno - usato dalla schermata di conferma del link. */
    public function findDuplicateMatch(int $leadId): ?array
    {
        foreach ($this->findDuplicateCandidates(100) as $candidate) {
            if ($candidate['bId'] === $leadId) {
                return $candidate;
            }
        }

        return null;
    }

    /** Nuovi contatti attivi creati da $since in poi (formato 'Y-m-d H:i:s') - usato dal Report. */
    public function countCreatedSince(string $since): int
    {
        $row = $this->db->fetchRow(
            'SELECT COUNT(*) AS total FROM leads WHERE status != 0 AND created_at >= :since',
            ['since' => $since]
        );

        return (int) ($row['total'] ?? 0);
    }

    private function displayLabel(?string $firstName, ?string $lastName, ?string $companyName): string
    {
        $name = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));

        return $name !== '' ? $name : ($companyName ?? '(senza nome)');
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Permission;

final class PermissionRepository extends AbstractRepository
{
    protected string $table = 'permissions';
    protected string $modelClass = Permission::class;

    /**
     * Righe pronte per ListPermissionsTool: nome del dominio (il gruppo -
     * "praticamente l'entita'", non un dato del model Permission stesso)
     * gia' unito, non un giro separato per ogni riga. Ordinate per
     * dominio poi categoria cosi' le voci dello stesso dominio restano
     * vicine anche prima di qualunque filtro lato client.
     *
     * @return array<int, array{id:int, code:string, name:string, category:?string, domain:string, domainCode:string}>
     */
    public function findAllWithDomain(): array
    {
        $sql = "SELECT p.id, p.code, p.name, p.category, g.name AS domain, g.code AS domainCode
                FROM permissions p
                JOIN permission_groups g ON g.id = p.permission_group_id
                WHERE p.status != 0
                ORDER BY g.sort_order, g.id, p.category, p.code";

        return $this->db->fetchAll($sql);
    }

    /**
     * Permessi del profilo, uniti ai grant diretti sull'utente, meno i
     * revoke diretti sull'utente.
     *
     * @return string[]
     */
    public function findEffectiveCodesForUser(int $userId, int $profileId): array
    {
        $sql = "SELECT code FROM (
                    SELECT p.code AS code
                    FROM permissions p
                    JOIN profile_permissions pp ON pp.permission_id = p.id AND pp.status != 0
                    WHERE pp.profile_id = :profileId AND p.status != 0

                    UNION

                    SELECT p.code AS code
                    FROM permissions p
                    JOIN user_permissions up ON up.permission_id = p.id AND up.status != 0
                    WHERE up.user_id = :userIdGrant AND up.effect = 'grant' AND p.status != 0
                ) AS granted
                WHERE code NOT IN (
                    SELECT p.code
                    FROM permissions p
                    JOIN user_permissions up ON up.permission_id = p.id AND up.status != 0
                    WHERE up.user_id = :userIdRevoke AND up.effect = 'revoke' AND p.status != 0
                )";

        $rows = $this->db->fetchAll($sql, [
            'profileId' => $profileId,
            'userIdGrant' => $userId,
            'userIdRevoke' => $userId,
        ]);

        return array_column($rows, 'code');
    }

    /**
     * Ricerca libera per il tool "modifica permesso" del prompt - stesso
     * principio di UserRepository::searchByName(): codice, nome o
     * descrizione che CONTENGONO il termine, con una ricerca fonetica di
     * riserva sul nome se quella esatta non trova nulla (vedi li' per il
     * perche').
     *
     * @return Permission[]
     */
    public function searchByName(string $query, int $limit = 10): array
    {
        $exact = $this->searchByNameExact($query, $limit);

        return $exact !== [] ? $exact : $this->searchByNamePhonetic($query, $limit);
    }

    private function searchByNameExact(string $query, int $limit): array
    {
        $sql = 'SELECT * FROM permissions WHERE status != 0 AND ('
            . 'code LIKE :q1 OR name LIKE :q2 OR description LIKE :q3'
            . ') ORDER BY id ASC LIMIT ' . max(1, $limit);
        $like = '%' . $query . '%';

        return array_map(
            static fn (array $row) => Permission::fromArray($row),
            $this->db->fetchAll($sql, ['q1' => $like, 'q2' => $like, 'q3' => $like])
        );
    }

    /**
     * Solo su 'name': 'code' e' un identificativo tecnico (usato nei
     * controlli ACL), non ha senso cercarlo per suono.
     */
    private function searchByNamePhonetic(string $query, int $limit): array
    {
        $words = array_slice(preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY), 0, 3);
        if ($words === []) {
            return [];
        }

        $conditions = [];
        $params = [];
        foreach ($words as $i => $word) {
            $key = "w{$i}";
            $conditions[] = "SOUNDEX(name) = SOUNDEX(:{$key})";
            $params[$key] = $word;
        }

        $sql = 'SELECT * FROM permissions WHERE status != 0 AND (' . implode(' OR ', $conditions) . ')'
            . ' ORDER BY id ASC LIMIT ' . max(1, $limit);

        return array_map(
            static fn (array $row) => Permission::fromArray($row),
            $this->db->fetchAll($sql, $params)
        );
    }
}

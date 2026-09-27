<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Dati di riferimento (geo), mai creati/modificati dall'app - solo
 * ricerca, per il campo autocomplete "Comune" (vedi public/js/
 * components/form.js e Index\Controller\GeoController). Estende
 * AbstractRepository per la convenzione di auto-wiring (Container come
 * unico argomento) e per $db, non per le sue query CRUD - find()/
 * insert()/ecc. restano ereditate ma inutilizzate apposta: un comune non
 * si crea/modifica/cancella da qui.
 */
final class MunicipalityRepository extends AbstractRepository
{
    protected string $table = 'municipalities';

    /** @return list<array{id:int,name:string,province_code:string}> */
    public function search(string $query, int $limit = 10): array
    {
        $sql = 'SELECT m.id, m.name, d.code AS province_code
                FROM municipalities m
                JOIN districts d ON d.id = m.district_id
                WHERE m.status != 0 AND m.name LIKE :q
                ORDER BY m.name ASC
                LIMIT ' . max(1, $limit);

        return $this->db->fetchAll($sql, ['q' => $query . '%']);
    }

    /** @return array{id:int,name:string,province_code:string}|null */
    public function findWithProvince(int $id): ?array
    {
        $sql = 'SELECT m.id, m.name, d.code AS province_code
                FROM municipalities m
                JOIN districts d ON d.id = m.district_id
                WHERE m.id = :id AND m.status != 0';

        return $this->db->fetchRow($sql, ['id' => $id]);
    }
}

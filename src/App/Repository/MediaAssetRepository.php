<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\MediaAsset;

final class MediaAssetRepository extends AbstractRepository
{
    protected string $table = 'media_assets';
    protected string $modelClass = MediaAsset::class;

    public function findByExternal(string $source, string $externalId): ?MediaAsset
    {
        $row = $this->db->fetchRow(
            'SELECT * FROM media_assets WHERE source = :s AND external_id = :e',
            ['s' => $source, 'e' => $externalId]
        );

        return $row !== null ? MediaAsset::fromArray($row) : null;
    }

    /** @return MediaAsset[] da analizzare, i piu' recenti prima (servono di piu') */
    public function findPending(int $limit): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM media_assets WHERE status != 0 AND analysis_status = 'da analizzare'
             ORDER BY modified_at DESC, id DESC LIMIT " . max(1, $limit)
        );

        return array_map(static fn (array $row) => MediaAsset::fromArray($row), $rows);
    }

    /** @return array<string,int> stato dell'analisi => quanti */
    public function countByStatus(): array
    {
        $result = [];
        foreach ($this->db->fetchAll('SELECT analysis_status, COUNT(*) AS n FROM media_assets WHERE status != 0 GROUP BY analysis_status') as $row) {
            $result[(string) $row['analysis_status']] = (int) $row['n'];
        }

        return $result;
    }

    /**
     * Ricerca: filtri esatti sulle colonne e parole cercate in nome,
     * cartella, descrizione e parole chiave. Prima chi contiene piu' parole
     * (il punteggio lo calcola MySQL), poi i piu' recenti.
     *
     * @param array{kind?:?string, customerId?:?int, noPeople?:bool, withPeople?:bool, setting?:?string, orientation?:?string} $filters
     * @param string[] $words
     * @return MediaAsset[]
     */
    public function search(array $filters, array $words, int $limit = 60): array
    {
        $where = ['status != 0'];
        $params = [];
        if (!empty($filters['kind'])) {
            $where[] = 'kind = :kind';
            $params['kind'] = $filters['kind'];
        }
        if (!empty($filters['customerId'])) {
            $where[] = 'customer_id = :customer';
            $params['customer'] = (int) $filters['customerId'];
        }
        if (!empty($filters['noPeople'])) {
            $where[] = 'people_count = 0';
        }
        if (!empty($filters['withPeople'])) {
            $where[] = 'people_count > 0';
        }
        if (!empty($filters['setting'])) {
            $where[] = 'setting = :setting';
            $params['setting'] = $filters['setting'];
        }
        if (!empty($filters['orientation'])) {
            $where[] = 'orientation = :orientation';
            $params['orientation'] = $filters['orientation'];
        }

        $score = '0';
        $any = [];
        foreach (array_values(array_slice($words, 0, 8)) as $i => $word) {
            $key = "w{$i}";
            $params[$key] = '%' . $word . '%';
            $match = "(CONCAT_WS(' ', name, folder, description, tags, occasion) LIKE :{$key}_m)";
            $params["{$key}_m"] = $params[$key];
            unset($params[$key]);
            $score .= " + {$match}";
            $any[] = $match;
        }
        // Ogni condizione ha il suo segnaposto: PDO non riusa lo stesso nome due volte.
        if ($any !== []) {
            $anyConditions = [];
            foreach ($any as $i => $condition) {
                $copy = str_replace(":w{$i}_m", ":w{$i}_a", $condition);
                $params["w{$i}_a"] = $params["w{$i}_m"];
                $anyConditions[] = $copy;
            }
            $where[] = '(' . implode(' OR ', $anyConditions) . ')';
        }

        $rows = $this->db->fetchAll(
            "SELECT *, ({$score}) AS score FROM media_assets WHERE " . implode(' AND ', $where)
            . ' ORDER BY score DESC, modified_at DESC, id DESC LIMIT ' . max(1, $limit),
            $params
        );

        return array_map(static fn (array $row) => MediaAsset::fromArray($row), $rows);
    }
}

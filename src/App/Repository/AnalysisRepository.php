<?php

declare(strict_types=1);

namespace App\Repository;

use App\Analysis\AnalysisException;
use App\Core\Container;
use App\Service\DbService;

/**
 * SQL dell'analisi libera (App\Analysis): lettura della struttura reale delle
 * tabelle ammesse e traduzione di una richiesta strutturata (vedi
 * App\Analysis\DataCatalog per cosa e' ammesso) in una SELECT con parametri.
 * Solo lettura: nessun nome di tabella o campo arriva qui senza essere stato
 * confrontato con il catalogo, i valori viaggiano sempre come parametri.
 */
final class AnalysisRepository
{
    private const GRANULARITY = [
        'day' => "DATE_FORMAT(%s, '%%Y-%%m-%%d')",
        'week' => "DATE_FORMAT(%s, '%%x-W%%v')",
        'month' => "DATE_FORMAT(%s, '%%Y-%%m')",
        'quarter' => "CONCAT(YEAR(%s), '-T', QUARTER(%s))",
        'year' => 'YEAR(%s)',
        'weekday' => 'DAYOFWEEK(%s)',
        'hour' => 'HOUR(%s)',
    ];
    private const PARTS = ['year' => 'YEAR(%s)', 'month' => 'MONTH(%s)', 'day' => 'DAY(%s)', 'weekday' => 'DAYOFWEEK(%s)', 'hour' => 'HOUR(%s)', 'date' => 'DATE(%s)'];
    private const OPERATORS = ['=', '!=', '>', '>=', '<', '<=', 'in', 'not_in', 'contains', 'is_null', 'not_null'];
    private const FUNCTIONS = ['count', 'count_distinct', 'sum', 'avg', 'min', 'max'];

    private DbService $db;

    public function __construct(Container $container)
    {
        $this->db = $container->get(DbService::class);
    }

    /** @return array<string, array<string, string>> tabella => campo => tipo SQL (data_type) */
    public function columns(array $tables): array
    {
        if ($tables === []) {
            return [];
        }
        $in = implode(',', array_map(static fn ($i) => ':t' . $i, array_keys($tables)));
        $params = [];
        foreach (array_values($tables) as $i => $table) {
            $params['t' . $i] = $table;
        }
        $result = [];
        foreach ($this->db->fetchAll(
            "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$in}) ORDER BY ORDINAL_POSITION",
            $params
        ) as $row) {
            $result[$row['TABLE_NAME']][$row['COLUMN_NAME']] = $row['DATA_TYPE'] . ($row['CHARACTER_MAXIMUM_LENGTH'] ? ':' . $row['CHARACTER_MAXIMUM_LENGTH'] : '');
        }

        return $result;
    }

    /**
     * Valori presenti per i campi "a categorie" (pochi valori diversi), cosi'
     * l'IA filtra e raggruppa con i valori veri ('in conciliazione', non
     * 'conciliazione').
     *
     * @param string[] $fields gia' validati dal catalogo
     * @return array<string, string[]>
     */
    public function categoryValues(string $table, array $fields, int $max = 15): array
    {
        $values = [];
        foreach ($fields as $field) {
            $rows = $this->db->fetchAll(
                "SELECT `{$field}` AS v, COUNT(*) AS n FROM `{$table}` WHERE status != 0 AND `{$field}` IS NOT NULL AND `{$field}` <> ''
                 GROUP BY `{$field}` ORDER BY n DESC LIMIT " . ($max + 1)
            );
            if ($rows !== [] && count($rows) <= $max) {
                $values[$field] = array_map(static fn (array $r) => (string) $r['v'], $rows);
            }
        }

        return $values;
    }

    public function countRows(string $table): int
    {
        return (int) ($this->db->fetchRow("SELECT COUNT(*) AS n FROM `{$table}` WHERE status != 0")['n'] ?? 0);
    }

    /**
     * Esegue una richiesta strutturata gia' controllata dal catalogo.
     *
     * @param array $query vedi DataCatalog::describeQueryLanguage()
     * @param array $table voce del catalogo: ['fields' => [...], 'label' => ...]
     * @param array $catalog catalogo completo (per le etichette dei collegamenti)
     * @return array{columns: string[], rows: list<array<string, mixed>>}
     */
    public function run(array $query, string $tableName, array $table, array $catalog): array
    {
        $params = [];
        $joins = [];
        $where = ['t.status != 0'];
        // Campo semplice ("airline") o percorso tra tabelle collegate
        // ("case_id.lead_id.airline"): ogni passo intermedio deve essere un
        // campo collegato del catalogo, l'ultimo un campo della tabella
        // raggiunta. I collegamenti diventano LEFT JOIN (riusati se ripetuti).
        $pathJoins = [];
        $resolve = function (string $path) use ($table, $tableName, $catalog, &$joins, &$pathJoins): array {
            $segments = array_values(array_filter(array_map([self::class, 'fieldName'], explode('.', $path)), 'strlen'));
            if ($segments === [] || count($segments) > 4) {
                throw new AnalysisException("Campo non valido: {$path}");
            }
            $alias = 't';
            $current = $table;
            $currentName = $tableName;
            $prefix = '';
            foreach ($segments as $i => $segment) {
                if (!isset($current['fields'][$segment])) {
                    throw new AnalysisException("Campo sconosciuto: {$currentName}.{$segment}");
                }
                if ($i === count($segments) - 1) {
                    return ["{$alias}.`{$segment}`", $current['fields'][$segment]];
                }
                $target = $current['fields'][$segment]['ref'] ?? null;
                if ($target === null || !isset($catalog[$target])) {
                    throw new AnalysisException("{$currentName}.{$segment} non e' un campo collegato");
                }
                $prefix .= $segment . '.';
                if (!isset($pathJoins[$prefix])) {
                    $pathJoins[$prefix] = 'p' . count($pathJoins);
                    $joins[] = "LEFT JOIN `{$target}` {$pathJoins[$prefix]} ON {$pathJoins[$prefix]}.id = {$alias}.`{$segment}` AND {$pathJoins[$prefix]}.status != 0";
                }
                $alias = $pathJoins[$prefix];
                $current = $catalog[$target];
                $currentName = $target;
            }
            throw new AnalysisException("Campo non valido: {$path}");
        };
        $field = static fn (string $name): string => $resolve($name)[0];

        foreach (array_slice((array) ($query['where'] ?? []), 0, 8) as $i => $condition) {
            $where[] = $this->condition((array) $condition, $field, $params, 'w' . $i);
        }
        if (!empty($query['period']['field'])) {
            $column = $field((string) $query['period']['field']);
            if (!empty($query['period']['from'])) {
                $where[] = "{$column} >= :pfrom";
                $params['pfrom'] = $this->date((string) $query['period']['from']);
            }
            if (!empty($query['period']['to'])) {
                $where[] = "{$column} < :pto + INTERVAL 1 DAY";
                $params['pto'] = $this->date((string) $query['period']['to']);
            }
        }

        $limit = max(1, min(200, (int) ($query['limit'] ?? 50)));
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        // Elenco di righe
        if (!empty($query['select'])) {
            $select = [];
            foreach (array_slice((array) $query['select'], 0, 8) as $i => $name) {
                [$expression] = $this->labelled((string) $name, $resolve, $catalog, $joins, 's' . $i);
                $select[] = "{$expression} AS `" . $this->alias((string) $name) . '`';
            }
            $order = '';
            if (!empty($query['orderBy']['field'])) {
                $order = ' ORDER BY ' . $field((string) $query['orderBy']['field']) . (strtolower((string) ($query['orderBy']['dir'] ?? 'desc')) === 'asc' ? ' ASC' : ' DESC');
            }
            $sql = 'SELECT ' . implode(', ', $select) . " FROM `{$tableName}` t " . implode(' ', $joins) . " {$whereSql}{$order} LIMIT {$limit}";

            return ['columns' => array_map(fn ($n) => $this->alias((string) $n), array_slice((array) $query['select'], 0, 8)), 'rows' => $this->db->fetchAll($sql, $params)];
        }

        // Aggregato (eventualmente raggruppato)
        $measure = (array) ($query['measure'] ?? ['fn' => 'count']);
        $fn = strtolower((string) ($measure['fn'] ?? 'count'));
        if (!in_array($fn, self::FUNCTIONS, true)) {
            throw new AnalysisException("Funzione non ammessa: {$fn}");
        }
        $value = match ($fn) {
            'count' => 'COUNT(*)',
            'count_distinct' => 'COUNT(DISTINCT ' . $field((string) ($measure['field'] ?? '')) . ')',
            default => strtoupper($fn) . '(' . $field((string) ($measure['field'] ?? '')) . ')',
        };

        $groupSelect = [];
        $groupBy = [];
        $columns = [];
        foreach (array_slice((array) ($query['groupBy'] ?? []), 0, 2) as $i => $group) {
            $group = is_array($group) ? $group : ['field' => (string) $group];
            $alias = $i === 0 ? 'group' : 'group2';
            if (!empty($group['by'])) {
                $pattern = self::GRANULARITY[(string) $group['by']] ?? throw new AnalysisException('Granularita\' non ammessa: ' . $group['by']);
                $column = $field((string) ($group['field'] ?? ''));
                $expression = str_contains($pattern, '%s, %s') || substr_count($pattern, '%s') > 1 ? sprintf($pattern, $column, $column) : sprintf($pattern, $column);
                $groupSelect[] = "{$expression} AS `{$alias}`";
                $groupBy[] = $expression;
            } else {
                [$expression, $key] = $this->labelled((string) ($group['field'] ?? ''), $resolve, $catalog, $joins, 'g' . $i);
                $groupSelect[] = "{$expression} AS `{$alias}`";
                $groupBy[] = $key;
                $groupBy[] = $expression;
            }
            $columns[] = $alias;
        }
        $columns[] = 'value';

        $order = match ((string) ($query['order'] ?? 'value_desc')) {
            'value_asc' => ' ORDER BY value ASC',
            'group_asc' => $groupBy !== [] ? ' ORDER BY `group` ASC' : '',
            'group_desc' => $groupBy !== [] ? ' ORDER BY `group` DESC' : '',
            default => $groupBy !== [] ? ' ORDER BY value DESC' : '',
        };
        $sql = 'SELECT ' . implode(', ', [...$groupSelect, "{$value} AS value"]) . " FROM `{$tableName}` t " . implode(' ', $joins)
            . " {$whereSql}" . ($groupBy !== [] ? ' GROUP BY ' . implode(', ', array_unique($groupBy)) : '') . "{$order} LIMIT {$limit}";

        $rows = $this->db->fetchAll($sql, $params);
        foreach ($rows as &$row) {
            foreach (['group', 'group2'] as $key) {
                if (array_key_exists($key, $row) && ($row[$key] === null || $row[$key] === '')) {
                    $row[$key] = 'non indicato';
                }
            }
            $row['value'] = $row['value'] === null ? 0 : (float) $row['value'];
            if (fmod($row['value'], 1.0) === 0.0) {
                $row['value'] = (int) $row['value'];
            } else {
                $row['value'] = round($row['value'], 2);
            }
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * Campo con etichetta leggibile: un collegamento (created_by, lead_id...)
     * diventa il nome dell'elemento collegato tramite LEFT JOIN.
     *
     * @return array{0: string, 1: string} espressione da mostrare, chiave di raggruppamento
     */
    private function labelled(string $name, callable $resolve, array $catalog, array &$joins, string $alias): array
    {
        [$column, $fieldInfo] = $resolve($name);
        $reference = $fieldInfo['ref'] ?? null;
        if ($reference === null || !isset($catalog[$reference])) {
            return [$column, $column];
        }
        $joins[] = "LEFT JOIN `{$reference}` {$alias} ON {$alias}.id = {$column}";
        $label = $catalog[$reference]['labelSql'] ?? 'id';

        return ['COALESCE(' . str_replace('{a}', $alias, $label) . ", CONCAT('#', {$column}))", $column];
    }

    private function condition(array $condition, callable $field, array &$params, string $key): string
    {
        $column = $field((string) ($condition['field'] ?? ''));
        if (!empty($condition['part'])) {
            $pattern = self::PARTS[(string) $condition['part']] ?? throw new AnalysisException('Parte di data non ammessa: ' . $condition['part']);
            $column = sprintf($pattern, $column);
        }
        $op = strtolower((string) ($condition['op'] ?? '='));
        if (!in_array($op, self::OPERATORS, true)) {
            throw new AnalysisException("Operatore non ammesso: {$op}");
        }
        $value = $condition['value'] ?? null;

        if ($op === 'is_null' || $op === 'not_null') {
            return $column . ($op === 'is_null' ? " IS NULL" : " IS NOT NULL");
        }
        if ($op === 'in' || $op === 'not_in') {
            $list = array_values(array_slice((array) $value, 0, 30));
            if ($list === []) {
                return $op === 'in' ? '1 = 0' : '1 = 1';
            }
            $names = [];
            foreach ($list as $i => $item) {
                $params["{$key}_{$i}"] = is_scalar($item) ? $item : '';
                $names[] = ":{$key}_{$i}";
            }

            return $column . ($op === 'in' ? ' IN (' : ' NOT IN (') . implode(',', $names) . ')';
        }
        if ($op === 'contains') {
            $params[$key] = '%' . (is_scalar($value) ? $value : '') . '%';

            return "{$column} LIKE :{$key}";
        }
        $params[$key] = is_scalar($value) ? $value : '';

        return "{$column} {$op} :{$key}";
    }

    private function date(string $value): string
    {
        $time = strtotime($value);
        if ($time === false) {
            throw new AnalysisException("Data non valida: {$value}");
        }

        return date('Y-m-d', $time);
    }

    private function alias(string $name): string
    {
        $parts = explode('.', $name);

        return self::fieldName((string) end($parts));
    }

    public static function fieldName(string $name): string
    {
        return preg_match('/[a-z][a-z0-9_]*/', strtolower(trim($name)), $m) ? $m[0] : '';
    }
}

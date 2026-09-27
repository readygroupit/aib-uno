<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Container;
use App\Event\EventDispatcher;
use App\Model\AbstractModel;
use App\Service\AuthService;
use App\Service\DbService;

/**
 * CRUD di base sopra DbService. Le sottoclassi impostano $table e
 * $modelClass. Convenzioni valide su ogni tabella del framework:
 * - insert()/update() timbrano automaticamente created_at/created_by
 *   o updated_at/updated_by leggendo l'utente corrente da AuthService;
 * - delete() non cancella mai la riga: la marca status = 0;
 * - find()/findAll() escludono di default le righe con status = 0
 *   (passare $includeDeleted = true per vederle comunque);
 * - insert()/update()/delete() emettono anche un evento ("{tabella}.created"
 *   / "{tabella}.updated" / "{tabella}.deleted") su EventDispatcher - ogni
 *   pacchetto, presente e futuro, ottiene l'aggancio event-driven gratis
 *   senza fare nulla di apposito, semplicemente scrivendo tramite un
 *   Repository (mai bypassato - vedi la disciplina query-solo-in-repository).
 */
abstract class AbstractRepository
{
    protected string $table;
    protected string $primaryKey = 'id';

    /** @var class-string<AbstractModel> */
    protected string $modelClass = AbstractModel::class;

    protected readonly DbService $db;
    protected readonly AuthService $auth;
    protected readonly EventDispatcher $events;

    public function __construct(Container $container)
    {
        $this->db = $container->get(DbService::class);
        $this->auth = $container->get(AuthService::class);
        $this->events = $container->get(EventDispatcher::class);
    }

    /** @return class-string<AbstractModel> */
    public function modelClass(): string
    {
        return $this->modelClass;
    }

    public function find(int $id, bool $includeDeleted = false): ?AbstractModel
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        if (!$includeDeleted) {
            $sql .= ' AND status != 0';
        }

        $row = $this->db->fetchRow($sql, ['id' => $id]);

        return $row ? ($this->modelClass)::fromArray($row) : null;
    }

    /**
     * @return AbstractModel[]
     */
    public function findAll(array $where = [], string $orderBy = '', bool $includeDeleted = false): array
    {
        [$whereSql, $params] = $this->buildWhere($where);

        if (!$includeDeleted) {
            $whereSql = $whereSql === '' ? 'WHERE status != 0' : "$whereSql AND status != 0";
        }

        $sql = "SELECT * FROM {$this->table} $whereSql" . ($orderBy !== '' ? " ORDER BY $orderBy" : '');

        return array_map(
            fn (array $row) => ($this->modelClass)::fromArray($row),
            $this->db->fetchAll($sql, $params)
        );
    }

    /**
     * Solo il conteggio, senza caricare righe - per chi (es. la card del
     * menu su ogni entita') deve solo mostrare "quanti ce ne sono", non
     * elencarli. paginate() farebbe comunque una query di COUNT identica
     * a questa PIU' una SELECT ... LIMIT che qui sarebbe sprecata.
     */
    public function count(array $where = [], bool $includeDeleted = false): int
    {
        [$whereSql, $params] = $this->buildWhere($where);
        if (!$includeDeleted) {
            $whereSql = $whereSql === '' ? 'WHERE status != 0' : "$whereSql AND status != 0";
        }

        $row = $this->db->fetchRow("SELECT COUNT(*) AS total FROM {$this->table} $whereSql", $params);

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Conteggio per valore di UNA colonna (es. quante righe per ogni
     * 'stage') - usato dalla schermata Report per riassumere senza dover
     * scrivere una query dedicata per ogni pacchetto. $column arriva
     * sempre da codice (mai da input utente): nessun rischio di
     * injection nonostante l'interpolazione diretta nell'SQL.
     *
     * @return array<string,int> valore colonna (stringa, '(vuoto)' se NULL) => conteggio, ordinato dal piu' frequente
     */
    public function countGroupedBy(string $column, bool $includeDeleted = false): array
    {
        $whereSql = $includeDeleted ? '' : 'WHERE status != 0';
        $rows = $this->db->fetchAll(
            "SELECT {$column} AS grp, COUNT(*) AS total FROM {$this->table} {$whereSql} GROUP BY {$column} ORDER BY total DESC"
        );

        $result = [];
        foreach ($rows as $row) {
            $key = $row['grp'] === null || $row['grp'] === '' ? '(vuoto)' : (string) $row['grp'];
            $result[$key] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * Conteggio per giorno negli ultimi 7 giorni (oggi incluso) su una
     * colonna data/datetime - sparkline reale per le card del cruscotto,
     * non il placeholder piatto usato altrove. Sempre 7 valori anche nei
     * giorni senza righe (0), dal piu' vecchio al piu' recente.
     *
     * @return list<int>
     */
    public function countPerDayLast7(string $dateColumn, bool $includeDeleted = false): array
    {
        $statusSql = $includeDeleted ? '' : 'AND status != 0';
        $since = date('Y-m-d', strtotime('-6 days'));
        $rows = $this->db->fetchAll(
            "SELECT DATE({$dateColumn}) AS d, COUNT(*) AS total FROM {$this->table}
             WHERE {$dateColumn} >= :since {$statusSql}
             GROUP BY DATE({$dateColumn})",
            ['since' => $since]
        );

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['d']] = (int) $row['total'];
        }

        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $series[] = $byDay[date('Y-m-d', strtotime("-{$i} days"))] ?? 0;
        }

        return $series;
    }

    /** Conteggio tra due date/datetime su una colonna (from incluso, to escluso) - confronti periodo-su-periodo (es. delta % settimana su settimana). */
    public function countBetween(string $dateColumn, string $from, string $to, bool $includeDeleted = false): int
    {
        $statusSql = $includeDeleted ? '' : 'AND status != 0';
        $row = $this->db->fetchRow(
            "SELECT COUNT(*) AS total FROM {$this->table} WHERE {$dateColumn} >= :from AND {$dateColumn} < :to {$statusSql}",
            ['from' => $from, 'to' => $to]
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return array{items: AbstractModel[], total: int, page: int, perPage: int}
     */
    public function paginate(int $page, int $perPage, array $where = [], string $orderBy = '', bool $includeDeleted = false): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        [$whereSql, $params] = $this->buildWhere($where);
        if (!$includeDeleted) {
            $whereSql = $whereSql === '' ? 'WHERE status != 0' : "$whereSql AND status != 0";
        }

        $totalRow = $this->db->fetchRow("SELECT COUNT(*) AS total FROM {$this->table} $whereSql", $params);
        $total = (int) ($totalRow['total'] ?? 0);

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM {$this->table} $whereSql"
            . ($orderBy !== '' ? " ORDER BY $orderBy" : '')
            . " LIMIT $perPage OFFSET $offset";

        $items = array_map(
            fn (array $row) => ($this->modelClass)::fromArray($row),
            $this->db->fetchAll($sql, $params)
        );

        return ['items' => $items, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    public function insert(array $data): int
    {
        $data['status'] ??= 1;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['created_by'] = $this->auth->currentUserId();

        $id = $this->db->insert($this->table, $data);

        $this->events->dispatch("{$this->table}.created", ['id' => $id] + $data);

        return $id;
    }

    public function update(int $id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['updated_by'] = $this->auth->currentUserId();

        $result = $this->db->update($this->table, $data, [$this->primaryKey => $id]);

        $this->events->dispatch("{$this->table}.updated", ['id' => $id] + $data);

        return $result;
    }

    /**
     * Eliminazione sempre logica: marca status = 0, non fa mai un DELETE reale.
     * Emette sia "{tabella}.updated" (da update()) sia "{tabella}.deleted":
     * un listener interessato a qualunque modifica ascolta il primo, uno
     * interessato specificamente alla cancellazione ascolta il secondo
     * senza dover ispezionare il payload per capire se status e' diventato 0.
     */
    public function delete(int $id): int
    {
        $result = $this->update($id, ['status' => 0]);

        $this->events->dispatch("{$this->table}.deleted", ['id' => $id]);

        return $result;
    }

    private function buildWhere(array $where): array
    {
        if (!$where) {
            return ['', []];
        }

        $clauses = [];
        $params = [];
        foreach ($where as $column => $value) {
            $clauses[] = "$column = :$column";
            $params[$column] = $value;
        }

        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }

    /**
     * Ricerca libera generica (era duplicata quasi identica in
     * UserRepository e LeadRepository: stessa struttura, cambiavano solo
     * le colonne). $likeColumns sono le colonne su cui cercare "contiene"
     * (es. nome, cognome, email); se la ricerca esatta non trova nulla e
     * $phoneticColumns non e' vuoto, riprova con SOUNDEX su quelle
     * colonne - utile per la dettatura vocale, dove un cognome puo'
     * essere trascritto con una grafia diversa dalla stessa parola (vedi
     * il commento originale in UserRepository per i dettagli
     * sull'algoritmo). $phoneticColumns tipicamente e' un sottoinsieme di
     * $likeColumns: SOUNDEX ha senso solo su parole lette a voce (nomi),
     * mai su email/codici/numeri.
     *
     * Pubblico (non solo uso interno): App\Prompt\Tool\
     * AbstractEditEntityTool la chiama direttamente su un
     * AbstractRepository generico, non necessariamente dall'interno
     * della gerarchia dei repository.
     *
     * @return AbstractModel[]
     */
    public function searchFreeText(array $likeColumns, string $query, int $limit = 10, array $phoneticColumns = []): array
    {
        $exact = $this->searchFreeTextExact($likeColumns, $query, $limit);
        if ($exact !== [] || $phoneticColumns === []) {
            return $exact;
        }

        return $this->searchFreeTextPhonetic($phoneticColumns, $query, $limit);
    }

    private function searchFreeTextExact(array $columns, string $query, int $limit): array
    {
        // Placeholder distinti (q0, q1, ...) non lo stesso ':query'
        // ripetuto: con PDO::ATTR_EMULATE_PREPARES a false su MySQL
        // riusare lo stesso nome di parametro piu' volte nella stessa
        // query non e' affidabile.
        $conditions = [];
        $params = [];
        foreach (array_values($columns) as $i => $column) {
            $key = "q{$i}";
            $conditions[] = "{$column} LIKE :{$key}";
            $params[$key] = '%' . $query . '%';
        }

        $sql = "SELECT * FROM {$this->table} WHERE status != 0 AND (" . implode(' OR ', $conditions) . ')'
            . " ORDER BY {$this->primaryKey} DESC LIMIT " . max(1, $limit);

        return array_map(
            fn (array $row) => ($this->modelClass)::fromArray($row),
            $this->db->fetchAll($sql, $params)
        );
    }

    private function searchFreeTextPhonetic(array $columns, string $query, int $limit): array
    {
        $words = array_slice(preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY), 0, 3);
        if ($words === []) {
            return [];
        }

        $conditions = [];
        $params = [];
        foreach ($words as $i => $word) {
            foreach (array_values($columns) as $j => $column) {
                $key = "w{$i}_{$j}";
                $conditions[] = "SOUNDEX({$column}) = SOUNDEX(:{$key})";
                $params[$key] = $word;
            }
        }

        $sql = "SELECT * FROM {$this->table} WHERE status != 0 AND (" . implode(' OR ', $conditions) . ')'
            . " ORDER BY {$this->primaryKey} DESC LIMIT " . max(1, $limit);

        return array_map(
            fn (array $row) => ($this->modelClass)::fromArray($row),
            $this->db->fetchAll($sql, $params)
        );
    }
}

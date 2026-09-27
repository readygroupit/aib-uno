<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Container;
use PDO;
use PDOStatement;

final class DbService
{
    private readonly PDO $pdo;

    public function __construct(private readonly Container $container)
    {
        $config = $container->get(Config::class);

        $this->pdo = new PDO(
            $config->get('db.dsn'),
            $config->get('db.username'),
            $config->get('db.password'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    public function fetchRow(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();

        return $row ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(static fn ($c) => ":$c", array_keys($data)));

        $this->query("INSERT INTO $table ($columns) VALUES ($placeholders)", $data);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int
    {
        $set = implode(', ', array_map(static fn ($c) => "$c = :set_$c", array_keys($data)));
        $params = [];
        foreach ($data as $column => $value) {
            $params["set_$column"] = $value;
        }

        [$whereSql, $whereParams] = $this->buildWhere($where);

        return $this->execute("UPDATE $table SET $set $whereSql", $params + $whereParams);
    }

    public function delete(string $table, array $where): int
    {
        [$whereSql, $whereParams] = $this->buildWhere($where);

        return $this->execute("DELETE FROM $table $whereSql", $whereParams);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function buildWhere(array $where): array
    {
        $clauses = [];
        $params = [];
        foreach ($where as $column => $value) {
            $clauses[] = "$column = :where_$column";
            $params["where_$column"] = $value;
        }

        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }

    private function query(string $sql, array $params): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $error = null;

        try {
            $stmt->execute($params);

            return $stmt;
        } catch (\PDOException $e) {
            $error = $e->getMessage();

            throw $e;
        } finally {
            $this->logQuery($sql, $error);
        }
    }

    /**
     * Chi ha chiamato la query non lo passa esplicitamente: si risale al
     * Repository (e al suo metodo) risalendo lo stack, lo stesso approccio
     * usato in Core per legare ogni query al 'model' che l'ha generata
     * senza dover instrumentare ogni singolo metodo dei Repository.
     */
    private function logQuery(string $sql, ?string $error): void
    {
        [$repositoryClass, $repositoryFunction] = $this->callerRepository();

        $this->container->get(LoggerService::class)->logQuery($sql, $error, $repositoryClass, $repositoryFunction);
    }

    /**
     * $frame['class'] darebbe la classe che DICHIARA il metodo (es.
     * AbstractRepository per find()/insert()/... mai sovrascritti dalle
     * sottoclassi) - serve invece la classe VERA dell'oggetto chiamante
     * (es. UserRepository), disponibile solo passando
     * DEBUG_BACKTRACE_PROVIDE_OBJECT e leggendo $frame['object'].
     */
    private function callerRepository(): array
    {
        $frames = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 8);

        foreach ($frames as $frame) {
            $object = $frame['object'] ?? null;
            if ($object instanceof \App\Repository\AbstractRepository) {
                return [get_class($object), $frame['function']];
            }
        }

        return [null, null];
    }
}

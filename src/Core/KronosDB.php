<?php
declare(strict_types=1);

namespace Kronos\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * KronosDB — Secure PDO wrapper.
 * All queries use prepared statements. Never interpolate user input.
 */
class KronosDB
{
    private PDO $pdo;
    private static ?KronosDB $instance = null;

    private function __construct(
        string $host,
        int $port,
        string $dbName,
        string $user,
        string $pass
    ) {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('KronosDB has not been initialized. Call KronosDB::init() first.');
        }
        return self::$instance;
    }

    public static function init(string $host, int $port, string $dbName, string $user, string $pass): self
    {
        self::$instance = new self($host, $port, $dbName, $user, $pass);
        return self::$instance;
    }

    /**
     * Execute a raw prepared statement. Returns PDOStatement.
     * ALWAYS use placeholders for user-supplied values.
     *
     * @param array<int|string, mixed> $params
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch all rows as associative arrays.
     *
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function getResults(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Fetch a single row.
     *
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function getRow(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Fetch a single column value from the first row.
     *
     * @param array<int|string, mixed> $params
     */
    public function getVar(string $sql, array $params = []): mixed
    {
        $row = $this->getRow($sql, $params);
        if ($row === null) {
            return null;
        }
        return reset($row);
    }

    /**
     * Insert a row and return the last insert ID.
     *
     * @param array<string, mixed> $data Column => value pairs
     */
    public function insert(string $table, array $data): int|string
    {
        $table = $this->sanitizeIdentifier($table);
        $columns = implode(', ', array_map([$this, 'sanitizeIdentifier'], array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $this->query("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})", array_values($data));
        return $this->pdo->lastInsertId();
    }

    /**
     * Update rows matching $where conditions.
     *
     * @param array<string, mixed> $data  Column => value pairs to set
     * @param array<string, mixed> $where Column => value pairs for WHERE clause
     */
    public function update(string $table, array $data, array $where): int
    {
        $table = $this->sanitizeIdentifier($table);
        $setClause = implode(', ', array_map(
            fn($col) => $this->sanitizeIdentifier($col) . ' = ?',
            array_keys($data)
        ));
        $whereClause = implode(' AND ', array_map(
            fn($col) => $this->sanitizeIdentifier($col) . ' = ?',
            array_keys($where)
        ));

        $params = array_merge(array_values($data), array_values($where));
        $stmt = $this->query("UPDATE {$table} SET {$setClause} WHERE {$whereClause}", $params);
        return $stmt->rowCount();
    }

    /**
     * Delete rows matching $where conditions.
     *
     * @param array<string, mixed> $where Column => value pairs for WHERE clause
     */
    public function delete(string $table, array $where): int
    {
        $table = $this->sanitizeIdentifier($table);
        $whereClause = implode(' AND ', array_map(
            fn($col) => $this->sanitizeIdentifier($col) . ' = ?',
            array_keys($where)
        ));

        $stmt = $this->query("DELETE FROM {$table} WHERE {$whereClause}", array_values($where));
        return $stmt->rowCount();
    }

    /**
     * Execute multiple SQL statements for schema creation (like dbDelta).
     * Returns array of results/errors per statement.
     *
     * @param string[] $statements
     * @return array<string, string>
     */
    public function runSchema(array $statements): array
    {
        $results = [];
        foreach ($statements as $sql) {
            $trimmed = trim($sql);
            if ($trimmed === '') {
                continue;
            }
            try {
                $this->pdo->exec($trimmed);
                $results[$trimmed] = 'ok';
            } catch (PDOException $e) {
                $results[$trimmed] = $e->getMessage();
            }
        }
        return $results;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    // ------------------------------------------------------------------
    // Transactions
    // ------------------------------------------------------------------

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * Execute $callback inside a transaction. Rolls back on any exception.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Sanitize a table or column identifier (allow only alphanumeric and underscores).
     */
    private function sanitizeIdentifier(string $identifier): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("Invalid SQL identifier: {$identifier}");
        }
        return "`{$identifier}`";
    }
}

<?php

declare(strict_types=1);

namespace FiberFlow\Database;

use Amp\Mysql\MysqlConfig;
use Amp\Mysql\MysqlConnectionPool;
use Amp\Mysql\MysqlResult;
use Amp\Mysql\MysqlStatement;
use Fiber;

/**
 * Async database connection using amphp/mysql.
 */
class AsyncDbConnection
{
    /**
     * Connection pool instance.
     */
    protected ?MysqlConnectionPool $pool = null;

    /**
     * Create a new async database connection.
     */
    public function __construct(
        protected array $config
    ) {
    }

    /**
     * Get or create the connection pool.
     */
    protected function getPool(): MysqlConnectionPool
    {
        if ($this->pool === null) {
            $config = MysqlConfig::fromString(
                sprintf(
                    'host=%s port=%d user=%s password=%s db=%s',
                    $this->config['host'] ?? 'localhost',
                    $this->config['port'] ?? 3306,
                    $this->config['username'] ?? 'root',
                    $this->config['password'] ?? '',
                    $this->config['database'] ?? 'test'
                )
            );

            $this->pool = new MysqlConnectionPool($config, $this->config['pool_size'] ?? 10);
        }

        return $this->pool;
    }

    /**
     * Execute a query and return the result.
     *
     * @param array<int|string, mixed> $params
     */
    public function query(string $sql, array $params = []): MysqlResult
    {
        $pool = $this->getPool();

        if (Fiber::getCurrent() !== null) {
            // We're in a Fiber, suspend during I/O
            return $pool->execute($sql, $params);
        }

        // Not in a Fiber, execute synchronously
        return $pool->execute($sql, $params);
    }

    /**
     * Prepare a statement for execution.
     */
    public function prepare(string $sql): MysqlStatement
    {
        $pool = $this->getPool();
        return $pool->prepare($sql);
    }

    /**
     * Execute a statement with parameters.
     *
     * @param array<int|string, mixed> $params
     */
    public function execute(string $sql, array $params = []): MysqlResult
    {
        return $this->query($sql, $params);
    }

    /**
     * Fetch all rows from a query.
     *
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $result = $this->query($sql, $params);
        $rows = [];

        foreach ($result as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Fetch a single row from a query.
     *
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params);
        
        foreach ($result as $row) {
            return $row;
        }

        return null;
    }

    /**
     * Insert a record and return the last insert ID.
     *
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $result = $this->query($sql, array_values($data));
        return $result->getLastInsertId();
    }

    /**
     * Update records and return the number of affected rows.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public function update(string $table, array $data, array $where): int
    {
        $setClauses = [];
        foreach (array_keys($data) as $column) {
            $setClauses[] = "{$column} = ?";
        }

        $whereClauses = [];
        foreach (array_keys($where) as $column) {
            $whereClauses[] = "{$column} = ?";
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $setClauses),
            implode(' AND ', $whereClauses)
        );

        $params = array_merge(array_values($data), array_values($where));
        $result = $this->query($sql, $params);
        
        return $result->getRowCount();
    }

    /**
     * Delete records and return the number of affected rows.
     *
     * @param array<string, mixed> $where
     */
    public function delete(string $table, array $where): int
    {
        $whereClauses = [];
        foreach (array_keys($where) as $column) {
            $whereClauses[] = "{$column} = ?";
        }

        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            implode(' AND ', $whereClauses)
        );

        $result = $this->query($sql, array_values($where));
        return $result->getRowCount();
    }

    /**
     * Begin a transaction.
     */
    public function beginTransaction(): void
    {
        $this->query('START TRANSACTION');
    }

    /**
     * Commit a transaction.
     */
    public function commit(): void
    {
        $this->query('COMMIT');
    }

    /**
     * Rollback a transaction.
     */
    public function rollback(): void
    {
        $this->query('ROLLBACK');
    }

    /**
     * Execute a callback within a transaction.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Close the connection pool.
     */
    public function close(): void
    {
        if ($this->pool !== null) {
            $this->pool->close();
            $this->pool = null;
        }
    }
}


<?php

declare(strict_types=1);

namespace FiberFlow\Database;

use Amp\Mysql\MysqlConfig;
use Amp\Mysql\MysqlConnectionPool;
use Fiber;
use Illuminate\Support\Facades\Config;
use Revolt\EventLoop;

class AsyncDbClient
{
    /**
     * Connection pool instance.
     */
    protected ?MysqlConnectionPool $pool = null;

    /**
     * Whether the client is enabled.
     */
    protected bool $enabled;

    /**
     * Create a new async database client instance.
     */
    public function __construct(
        protected int $poolSize = 10,
        protected int $timeout = 5,
    ) {
        $this->enabled = config('fiberflow.database.enabled', false);

        if ($this->enabled) {
            $this->initializePool();
        }
    }

    /**
     * Initialize the connection pool.
     */
    protected function initializePool(): void
    {
        $config = MysqlConfig::fromString(
            sprintf(
                'host=%s port=%s user=%s password=%s db=%s',
                Config::get('database.connections.mysql.host', '127.0.0.1'),
                Config::get('database.connections.mysql.port', 3306),
                Config::get('database.connections.mysql.username', 'root'),
                Config::get('database.connections.mysql.password', ''),
                Config::get('database.connections.mysql.database', 'laravel'),
            ),
        );

        $this->pool = new MysqlConnectionPool($config, $this->poolSize);
    }

    /**
     * Execute a SELECT query.
     *
     * @param array<int, mixed> $bindings
     *
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        if (! $this->enabled) {
            throw new \RuntimeException('AsyncDb is not enabled. Set FIBERFLOW_DB_ENABLED=true');
        }

        return $this->query($sql, $bindings);
    }

    /**
     * Execute an INSERT query.
     *
     * @param array<int, mixed> $bindings
     */
    public function insert(string $sql, array $bindings = []): int
    {
        if (! $this->enabled) {
            throw new \RuntimeException('AsyncDb is not enabled. Set FIBERFLOW_DB_ENABLED=true');
        }

        $this->query($sql, $bindings);

        return $this->pool->getLastInsertId();
    }

    /**
     * Execute an UPDATE query.
     *
     * @param array<int, mixed> $bindings
     */
    public function update(string $sql, array $bindings = []): int
    {
        if (! $this->enabled) {
            throw new \RuntimeException('AsyncDb is not enabled. Set FIBERFLOW_DB_ENABLED=true');
        }

        $result = $this->query($sql, $bindings);

        return count($result);
    }

    /**
     * Execute a DELETE query.
     *
     * @param array<int, mixed> $bindings
     */
    public function delete(string $sql, array $bindings = []): int
    {
        if (! $this->enabled) {
            throw new \RuntimeException('AsyncDb is not enabled. Set FIBERFLOW_DB_ENABLED=true');
        }

        $result = $this->query($sql, $bindings);

        return count($result);
    }

    /**
     * Execute a raw query.
     *
     * @param array<int, mixed> $bindings
     *
     * @return array<int, array<string, mixed>>
     */
    public function query(string $sql, array $bindings = []): array
    {
        if (! $this->enabled) {
            throw new \RuntimeException('AsyncDb is not enabled. Set FIBERFLOW_DB_ENABLED=true');
        }

        // If we're in a Fiber, suspend and resume when query completes
        if (Fiber::getCurrent() !== null) {
            return $this->queryAsync($sql, $bindings);
        }

        // Otherwise, make a blocking query
        return $this->querySync($sql, $bindings);
    }

    /**
     * Execute an async query (suspends the current Fiber).
     *
     * @param array<int, mixed> $bindings
     *
     * @return array<int, array<string, mixed>>
     */
    protected function queryAsync(string $sql, array $bindings): array
    {
        $result = null;
        $exception = null;

        EventLoop::queue(function () use ($sql, $bindings, &$result, &$exception): void {
            try {
                $statement = $this->pool->prepare($sql);
                $queryResult = $statement->execute($bindings);

                $result = [];
                while ($row = $queryResult->fetchRow()) {
                    $result[] = $row;
                }
            } catch (\Throwable $e) {
                $exception = $e;
            }
        });

        // Suspend the current Fiber
        Fiber::suspend();

        if ($exception !== null) {
            throw $exception;
        }

        return $result ?? [];
    }

    /**
     * Execute a synchronous query (blocks).
     *
     * @param array<int, mixed> $bindings
     *
     * @return array<int, array<string, mixed>>
     */
    protected function querySync(string $sql, array $bindings): array
    {
        $statement = $this->pool->prepare($sql);
        $queryResult = $statement->execute($bindings);

        $result = [];
        while ($row = $queryResult->fetchRow()) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Close the connection pool.
     */
    public function close(): void
    {
        if ($this->pool !== null) {
            $this->pool->close();
        }
    }
}

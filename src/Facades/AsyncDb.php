<?php

declare(strict_types=1);

namespace FiberFlow\Facades;

use FiberFlow\Database\AsyncDbConnection;
use FiberFlow\Database\AsyncQueryBuilder;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \FiberFlow\Database\AsyncQueryBuilder table(string $table)
 * @method static \Amp\Mysql\MysqlResult query(string $sql, array $params = [])
 * @method static array fetchAll(string $sql, array $params = [])
 * @method static array|null fetchOne(string $sql, array $params = [])
 * @method static int insert(string $table, array $data)
 * @method static int update(string $table, array $data, array $where)
 * @method static int delete(string $table, array $where)
 * @method static \Amp\Mysql\MysqlStatement prepare(string $sql)
 * @method static void close()
 *
 * @see \FiberFlow\Database\AsyncDbConnection
 */
class AsyncDb extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return AsyncDbConnection::class;
    }

    /**
     * Create a query builder for a table.
     */
    public static function table(string $table): AsyncQueryBuilder
    {
        return new AsyncQueryBuilder(static::getFacadeRoot(), $table);
    }
}

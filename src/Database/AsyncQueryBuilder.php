<?php

declare(strict_types=1);

namespace FiberFlow\Database;

/**
 * Async query builder for database operations.
 */
class AsyncQueryBuilder
{
    /**
     * WHERE clauses.
     *
     * @var array<int, array{column: string, operator: string, value: mixed}>
     */
    protected array $wheres = [];

    /**
     * SELECT columns.
     *
     * @var array<int, string>
     */
    protected array $selects = ['*'];

    /**
     * ORDER BY clauses.
     *
     * @var array<int, array{column: string, direction: string}>
     */
    protected array $orders = [];

    /**
     * LIMIT value.
     */
    protected ?int $limit = null;

    /**
     * OFFSET value.
     */
    protected ?int $offset = null;

    /**
     * Create a new query builder instance.
     */
    public function __construct(
        protected AsyncDbConnection $connection,
        protected string $table
    ) {
    }

    /**
     * Set the columns to select.
     */
    public function select(string ...$columns): self
    {
        $this->selects = $columns;
        return $this;
    }

    /**
     * Add a WHERE clause.
     */
    public function where(string $column, string|int|float $operator, mixed $value = null): self
    {
        // If only 2 arguments, assume '=' operator
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'column' => $column,
            'operator' => (string) $operator,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Add an ORDER BY clause.
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction),
        ];

        return $this;
    }

    /**
     * Set the LIMIT.
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set the OFFSET.
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Execute the query and get all results.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        [$sql, $params] = $this->toSql();
        return $this->connection->fetchAll($sql, $params);
    }

    /**
     * Execute the query and get the first result.
     *
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $this->limit(1);
        [$sql, $params] = $this->toSql();
        return $this->connection->fetchOne($sql, $params);
    }

    /**
     * Insert a record.
     *
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        return $this->connection->insert($this->table, $data);
    }

    /**
     * Update records.
     *
     * @param array<string, mixed> $data
     */
    public function update(array $data): int
    {
        $where = $this->buildWhereArray();
        return $this->connection->update($this->table, $data, $where);
    }

    /**
     * Delete records.
     */
    public function delete(): int
    {
        $where = $this->buildWhereArray();
        return $this->connection->delete($this->table, $where);
    }

    /**
     * Build the SQL query and parameters.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function toSql(): array
    {
        $sql = sprintf('SELECT %s FROM %s', implode(', ', $this->selects), $this->table);
        $params = [];

        // Add WHERE clauses
        if (!empty($this->wheres)) {
            $whereClauses = [];
            foreach ($this->wheres as $where) {
                $whereClauses[] = "{$where['column']} {$where['operator']} ?";
                $params[] = $where['value'];
            }
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        // Add ORDER BY
        if (!empty($this->orders)) {
            $orderClauses = [];
            foreach ($this->orders as $order) {
                $orderClauses[] = "{$order['column']} {$order['direction']}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        // Add LIMIT
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        // Add OFFSET
        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return [$sql, $params];
    }

    /**
     * Build WHERE array for update/delete operations.
     *
     * @return array<string, mixed>
     */
    protected function buildWhereArray(): array
    {
        $where = [];
        foreach ($this->wheres as $clause) {
            if ($clause['operator'] === '=') {
                $where[$clause['column']] = $clause['value'];
            }
        }
        return $where;
    }
}


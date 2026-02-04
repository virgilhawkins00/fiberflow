<?php

declare(strict_types=1);

namespace FiberFlow\Queue\Drivers;

use FiberFlow\Database\AsyncDbConnection;
use FiberFlow\Queue\Contracts\AsyncQueueDriver;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Jobs\DatabaseJob;

/**
 * Database queue driver using async database operations.
 */
class DatabaseQueueDriver implements AsyncQueueDriver
{
    /**
     * Jobs table name.
     */
    protected string $table = 'jobs';

    /**
     * Create a new database queue driver.
     */
    public function __construct(
        protected AsyncDbConnection $connection,
        protected string $default = 'default'
    ) {
    }

    /**
     * Push a job onto the queue.
     */
    public function push(string $queue, string $payload, int $delay = 0): ?string
    {
        $availableAt = time() + $delay;

        $id = $this->connection->insert($this->table, [
            'queue' => $queue,
            'payload' => $payload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $availableAt,
            'created_at' => time(),
        ]);

        return (string) $id;
    }

    /**
     * Pop the next job from the queue.
     */
    public function pop(string $queue): ?Job
    {
        // Get next available job
        $job = $this->connection->fetchOne(
            "SELECT * FROM {$this->table} 
             WHERE queue = ? 
             AND available_at <= ? 
             AND reserved_at IS NULL 
             ORDER BY id ASC 
             LIMIT 1",
            [$queue, time()]
        );

        if ($job === null) {
            return null;
        }

        // Reserve the job
        $this->connection->update(
            $this->table,
            [
                'reserved_at' => time(),
                'attempts' => $job['attempts'] + 1,
            ],
            ['id' => $job['id']]
        );

        return new DatabaseJob(
            app(),
            app('db')->connection(),
            $job,
            app('queue')->connection(),
            $queue
        );
    }

    /**
     * Delete a job from the queue.
     */
    public function delete(string $queue, string $jobId): void
    {
        $this->connection->delete($this->table, ['id' => (int) $jobId]);
    }

    /**
     * Release a job back to the queue.
     */
    public function release(string $queue, string $jobId, int $delay = 0): void
    {
        $availableAt = time() + $delay;

        $this->connection->update(
            $this->table,
            [
                'reserved_at' => null,
                'available_at' => $availableAt,
            ],
            ['id' => (int) $jobId]
        );
    }

    /**
     * Get the size of the queue.
     */
    public function size(string $queue): int
    {
        $result = $this->connection->fetchOne(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE queue = ?",
            [$queue]
        );

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Clear all jobs from the queue.
     */
    public function clear(string $queue): void
    {
        $this->connection->delete($this->table, ['queue' => $queue]);
    }

    /**
     * Get driver name.
     */
    public function getName(): string
    {
        return 'database';
    }

    /**
     * Check if driver supports async operations.
     */
    public function isAsync(): bool
    {
        return true;
    }

    /**
     * Close the driver connection.
     */
    public function close(): void
    {
        $this->connection->close();
    }

    /**
     * Set the jobs table name.
     */
    public function setTable(string $table): self
    {
        $this->table = $table;
        return $this;
    }
}


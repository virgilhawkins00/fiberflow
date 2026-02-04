<?php

declare(strict_types=1);

namespace FiberFlow\Queue\Contracts;

use Illuminate\Contracts\Queue\Job;

/**
 * Interface for async queue drivers.
 *
 * All FiberFlow queue drivers must implement this interface.
 */
interface AsyncQueueDriver
{
    /**
     * Push a job onto the queue.
     *
     * @param string $queue Queue name
     * @param string $payload Serialized job payload
     * @param int $delay Delay in seconds (0 for immediate)
     * @return string|null Job ID
     */
    public function push(string $queue, string $payload, int $delay = 0): ?string;

    /**
     * Pop the next job from the queue.
     *
     * @param string $queue Queue name
     * @return Job|null
     */
    public function pop(string $queue): ?Job;

    /**
     * Delete a job from the queue.
     *
     * @param string $queue Queue name
     * @param string $jobId Job ID
     */
    public function delete(string $queue, string $jobId): void;

    /**
     * Release a job back to the queue.
     *
     * @param string $queue Queue name
     * @param string $jobId Job ID
     * @param int $delay Delay in seconds before job becomes available again
     */
    public function release(string $queue, string $jobId, int $delay = 0): void;

    /**
     * Get the size of the queue.
     *
     * @param string $queue Queue name
     * @return int Number of jobs in queue
     */
    public function size(string $queue): int;

    /**
     * Clear all jobs from the queue.
     *
     * @param string $queue Queue name
     */
    public function clear(string $queue): void;

    /**
     * Get driver name.
     */
    public function getName(): string;

    /**
     * Check if driver supports async operations.
     */
    public function isAsync(): bool;

    /**
     * Close the driver connection.
     */
    public function close(): void;
}


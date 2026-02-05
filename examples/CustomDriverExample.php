<?php

declare(strict_types=1);

namespace FiberFlow\Examples;

use FiberFlow\Queue\Contracts\AsyncQueueDriver;
use FiberFlow\Queue\DriverManager;
use Illuminate\Contracts\Queue\Job;

/**
 * Example demonstrating custom queue driver implementation.
 *
 * This example shows how to:
 * - Implement a custom queue driver
 * - Register it with the DriverManager
 * - Use it in your application
 */

// ============================================================================
// Example 1: Simple In-Memory Queue Driver
// ============================================================================

class InMemoryQueueDriver implements AsyncQueueDriver
{
    /**
     * In-memory storage for jobs.
     *
     * @var array<string, array<int, array{id: string, payload: string, availableAt: int}>>
     */
    protected array $queues = [];

    /**
     * Job ID counter.
     */
    protected int $counter = 0;

    /**
     * Push a job onto the queue.
     */
    public function push(string $queue, string $payload, int $delay = 0): ?string
    {
        if (! isset($this->queues[$queue])) {
            $this->queues[$queue] = [];
        }

        $jobId = 'job_'.$this->counter++;
        $availableAt = time() + $delay;

        $this->queues[$queue][] = [
            'id' => $jobId,
            'payload' => $payload,
            'availableAt' => $availableAt,
        ];

        return $jobId;
    }

    /**
     * Pop the next job from the queue.
     */
    public function pop(string $queue): ?Job
    {
        if (! isset($this->queues[$queue]) || empty($this->queues[$queue])) {
            return null;
        }

        $now = time();

        foreach ($this->queues[$queue] as $index => $job) {
            if ($job['availableAt'] <= $now) {
                unset($this->queues[$queue][$index]);
                $this->queues[$queue] = array_values($this->queues[$queue]);

                // Create a simple job wrapper
                return new class($job['payload'], $job['id']) implements Job
                {
                    public function __construct(
                        protected string $payload,
                        protected string $jobId,
                    ) {}

                    public function fire()
                    { /* Process job */
                    }

                    public function delete() {}

                    public function release($delay = 0) {}

                    public function attempts()
                    {
                        return 1;
                    }

                    public function getJobId()
                    {
                        return $this->jobId;
                    }

                    public function getRawBody()
                    {
                        return $this->payload;
                    }

                    public function getName()
                    {
                        return 'InMemoryJob';
                    }

                    public function getConnectionName()
                    {
                        return 'memory';
                    }

                    public function getQueue()
                    {
                        return 'default';
                    }

                    public function isDeleted()
                    {
                        return false;
                    }

                    public function isReleased()
                    {
                        return false;
                    }

                    public function isDeletedOrReleased()
                    {
                        return false;
                    }
                };
            }
        }

        return null;
    }

    /**
     * Delete a job from the queue.
     */
    public function delete(string $queue, string $jobId): void
    {
        if (! isset($this->queues[$queue])) {
            return;
        }

        foreach ($this->queues[$queue] as $index => $job) {
            if ($job['id'] === $jobId) {
                unset($this->queues[$queue][$index]);
                $this->queues[$queue] = array_values($this->queues[$queue]);
                break;
            }
        }
    }

    /**
     * Release a job back to the queue.
     */
    public function release(string $queue, string $jobId, int $delay = 0): void
    {
        // For in-memory, we just update the availableAt time
        if (! isset($this->queues[$queue])) {
            return;
        }

        foreach ($this->queues[$queue] as &$job) {
            if ($job['id'] === $jobId) {
                $job['availableAt'] = time() + $delay;
                break;
            }
        }
    }

    /**
     * Get the size of the queue.
     */
    public function size(string $queue): int
    {
        return count($this->queues[$queue] ?? []);
    }

    /**
     * Clear all jobs from the queue.
     */
    public function clear(string $queue): void
    {
        unset($this->queues[$queue]);
    }

    /**
     * Get driver name.
     */
    public function getName(): string
    {
        return 'memory';
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
        $this->queues = [];
    }
}

// ============================================================================
// Example 2: Using Custom Driver
// ============================================================================

function customDriverExample(): void
{
    $manager = new DriverManager;

    // Register custom driver
    $manager->register('memory', InMemoryQueueDriver::class);

    // Get driver instance
    $driver = $manager->driver('memory');

    // Push jobs
    echo "Pushing jobs...\n";
    $driver->push('default', json_encode(['task' => 'send_email', 'to' => 'user@example.com']));
    $driver->push('default', json_encode(['task' => 'process_image', 'id' => 123]));
    $driver->push('default', json_encode(['task' => 'generate_report', 'user_id' => 456]), delay: 60);

    echo "Queue size: {$driver->size('default')}\n\n";

    // Process jobs
    echo "Processing jobs...\n";
    while ($job = $driver->pop('default')) {
        echo "Processing: {$job->getName()} - {$job->getRawBody()}\n";
        $job->delete();
    }

    echo "\nQueue size after processing: {$driver->size('default')}\n";

    // Clean up
    $driver->close();
}

// Run example
if (php_sapi_name() === 'cli') {
    customDriverExample();
}

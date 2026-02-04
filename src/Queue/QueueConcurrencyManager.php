<?php

declare(strict_types=1);

namespace FiberFlow\Queue;

/**
 * Manages concurrency limits per queue.
 *
 * Allows different queues to have different concurrency limits.
 */
class QueueConcurrencyManager
{
    /**
     * Active job counts per queue.
     *
     * @var array<string, int>
     */
    protected array $activeJobs = [];

    /**
     * Concurrency limits per queue.
     *
     * @var array<string, int>
     */
    protected array $limits = [];

    /**
     * Default concurrency limit.
     */
    protected int $defaultLimit;

    /**
     * Create a new queue concurrency manager.
     *
     * @param int $defaultLimit Default concurrency limit for queues
     */
    public function __construct(int $defaultLimit = 50)
    {
        $this->defaultLimit = $defaultLimit;
    }

    /**
     * Set concurrency limit for a specific queue.
     */
    public function setLimit(string $queue, int $limit): void
    {
        $this->limits[$queue] = $limit;
    }

    /**
     * Get concurrency limit for a queue.
     */
    public function getLimit(string $queue): int
    {
        return $this->limits[$queue] ?? $this->defaultLimit;
    }

    /**
     * Check if a queue can accept more jobs.
     */
    public function canProcess(string $queue): bool
    {
        $active = $this->activeJobs[$queue] ?? 0;
        $limit = $this->getLimit($queue);

        return $active < $limit;
    }

    /**
     * Increment active job count for a queue.
     */
    public function increment(string $queue): void
    {
        if (!isset($this->activeJobs[$queue])) {
            $this->activeJobs[$queue] = 0;
        }

        $this->activeJobs[$queue]++;
    }

    /**
     * Decrement active job count for a queue.
     */
    public function decrement(string $queue): void
    {
        if (isset($this->activeJobs[$queue]) && $this->activeJobs[$queue] > 0) {
            $this->activeJobs[$queue]--;
        }
    }

    /**
     * Get active job count for a queue.
     */
    public function getActiveCount(string $queue): int
    {
        return $this->activeJobs[$queue] ?? 0;
    }

    /**
     * Get available slots for a queue.
     */
    public function getAvailableSlots(string $queue): int
    {
        $active = $this->getActiveCount($queue);
        $limit = $this->getLimit($queue);

        return max(0, $limit - $active);
    }

    /**
     * Get all active job counts.
     *
     * @return array<string, int>
     */
    public function getAllActiveCounts(): array
    {
        return $this->activeJobs;
    }

    /**
     * Get all limits.
     *
     * @return array<string, int>
     */
    public function getAllLimits(): array
    {
        return $this->limits;
    }

    /**
     * Reset all counters.
     */
    public function reset(): void
    {
        $this->activeJobs = [];
    }

    /**
     * Reset counter for a specific queue.
     */
    public function resetQueue(string $queue): void
    {
        unset($this->activeJobs[$queue]);
    }
}


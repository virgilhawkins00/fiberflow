<?php

declare(strict_types=1);

namespace FiberFlow\Queue;

use Illuminate\Contracts\Queue\Job;
use SplPriorityQueue;

/**
 * Priority queue implementation for FiberFlow.
 *
 * Jobs with higher priority are processed first.
 */
class PriorityQueue
{
    /**
     * Internal priority queue.
     */
    protected SplPriorityQueue $queue;

    /**
     * Job counter for stable sorting.
     */
    protected int $counter = 0;

    /**
     * Create a new priority queue instance.
     */
    public function __construct()
    {
        $this->queue = new SplPriorityQueue;
    }

    /**
     * Push a job onto the queue with a priority.
     *
     * Higher priority values are processed first.
     * Jobs with the same priority are processed in FIFO order.
     */
    public function push(Job $job, int $priority = 0): void
    {
        // Use counter for stable sorting (FIFO for same priority)
        $this->queue->insert($job, [$priority, -$this->counter++]);
    }

    /**
     * Pop the highest priority job from the queue.
     */
    public function pop(): ?Job
    {
        if ($this->queue->isEmpty()) {
            return null;
        }

        return $this->queue->extract();
    }

    /**
     * Check if the queue is empty.
     */
    public function isEmpty(): bool
    {
        return $this->queue->isEmpty();
    }

    /**
     * Get the number of jobs in the queue.
     */
    public function count(): int
    {
        return $this->queue->count();
    }

    /**
     * Clear all jobs from the queue.
     */
    public function clear(): void
    {
        $this->queue = new SplPriorityQueue;
        $this->counter = 0;
    }
}

<?php

declare(strict_types=1);

namespace FiberFlow\Queue;

use Illuminate\Contracts\Queue\Job;

/**
 * Delayed job queue implementation for FiberFlow.
 *
 * Jobs are held until their scheduled time arrives.
 */
class DelayedJobQueue
{
    /**
     * Delayed jobs storage.
     *
     * @var array<int, array{job: Job, availableAt: float}>
     */
    protected array $jobs = [];

    /**
     * Push a job onto the delayed queue.
     *
     * @param Job $job The job to delay
     * @param int $delay Delay in seconds
     */
    public function push(Job $job, int $delay): void
    {
        $this->jobs[] = [
            'job' => $job,
            'availableAt' => microtime(true) + $delay,
        ];

        // Sort by availableAt for efficient polling
        usort($this->jobs, fn ($a, $b) => $a['availableAt'] <=> $b['availableAt']);
    }

    /**
     * Get all jobs that are ready to be processed.
     *
     * @return array<int, Job>
     */
    public function getReadyJobs(): array
    {
        $now = microtime(true);
        $ready = [];

        foreach ($this->jobs as $index => $item) {
            if ($item['availableAt'] <= $now) {
                $ready[] = $item['job'];
                unset($this->jobs[$index]);
            } else {
                // Since sorted, no more ready jobs
                break;
            }
        }

        // Re-index array
        $this->jobs = array_values($this->jobs);

        return $ready;
    }

    /**
     * Get the next job that will be ready.
     */
    public function getNextJob(): ?Job
    {
        $ready = $this->getReadyJobs();

        return $ready[0] ?? null;
    }

    /**
     * Get the time until the next job is ready (in seconds).
     */
    public function getTimeUntilNext(): ?float
    {
        if (empty($this->jobs)) {
            return null;
        }

        $now = microtime(true);
        $next = $this->jobs[0]['availableAt'];

        return max(0, $next - $now);
    }

    /**
     * Check if there are any delayed jobs.
     */
    public function isEmpty(): bool
    {
        return empty($this->jobs);
    }

    /**
     * Get the number of delayed jobs.
     */
    public function count(): int
    {
        return count($this->jobs);
    }

    /**
     * Clear all delayed jobs.
     */
    public function clear(): void
    {
        $this->jobs = [];
    }

    /**
     * Get all delayed jobs (for inspection).
     *
     * @return array<int, array{job: Job, availableAt: float}>
     */
    public function all(): array
    {
        return $this->jobs;
    }
}

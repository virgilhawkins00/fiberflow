<?php

declare(strict_types=1);

namespace FiberFlow\Queue;

use Illuminate\Contracts\Queue\Job;

/**
 * Job batch for grouping and tracking multiple jobs.
 *
 * Allows tracking completion, failures, and callbacks for a group of jobs.
 */
class JobBatch
{
    /**
     * Batch jobs.
     *
     * @var array<int, Job>
     */
    protected array $jobs = [];

    /**
     * Completed job IDs.
     *
     * @var array<int, string>
     */
    protected array $completed = [];

    /**
     * Failed job IDs.
     *
     * @var array<int, string>
     */
    protected array $failed = [];

    /**
     * Callbacks to run when batch completes.
     *
     * @var array<int, callable>
     */
    protected array $thenCallbacks = [];

    /**
     * Callbacks to run when batch fails.
     *
     * @var array<int, callable>
     */
    protected array $catchCallbacks = [];

    /**
     * Callbacks to run when batch finishes (success or failure).
     *
     * @var array<int, callable>
     */
    protected array $finallyCallbacks = [];

    /**
     * Create a new job batch.
     */
    public function __construct(
        protected string $id,
        protected string $name = '',
    ) {}

    /**
     * Add a job to the batch.
     */
    public function add(Job $job): self
    {
        $this->jobs[] = $job;

        return $this;
    }

    /**
     * Mark a job as completed.
     */
    public function markCompleted(string $jobId): void
    {
        if (! in_array($jobId, $this->completed)) {
            $this->completed[] = $jobId;
        }

        $this->checkCompletion();
    }

    /**
     * Mark a job as failed.
     */
    public function markFailed(string $jobId): void
    {
        if (! in_array($jobId, $this->failed)) {
            $this->failed[] = $jobId;
        }

        $this->checkCompletion();
    }

    /**
     * Register a callback to run when batch completes successfully.
     */
    public function then(callable $callback): self
    {
        $this->thenCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to run when batch fails.
     */
    public function catch(callable $callback): self
    {
        $this->catchCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to run when batch finishes.
     */
    public function finally(callable $callback): self
    {
        $this->finallyCallbacks[] = $callback;

        return $this;
    }

    /**
     * Check if the batch is complete and run callbacks.
     */
    protected function checkCompletion(): void
    {
        if (! $this->isFinished()) {
            return;
        }

        // Run finally callbacks first
        foreach ($this->finallyCallbacks as $callback) {
            $callback($this);
        }

        // Run success or failure callbacks
        if ($this->hasFailures()) {
            foreach ($this->catchCallbacks as $callback) {
                $callback($this);
            }
        } else {
            foreach ($this->thenCallbacks as $callback) {
                $callback($this);
            }
        }
    }

    /**
     * Check if the batch is finished (all jobs completed or failed).
     */
    public function isFinished(): bool
    {
        return count($this->completed) + count($this->failed) >= count($this->jobs);
    }

    /**
     * Check if the batch has any failures.
     */
    public function hasFailures(): bool
    {
        return ! empty($this->failed);
    }

    /**
     * Get batch progress (0-100).
     */
    public function progress(): float
    {
        if (empty($this->jobs)) {
            return 100.0;
        }

        $processed = count($this->completed) + count($this->failed);

        return ($processed / count($this->jobs)) * 100;
    }

    /**
     * Get batch ID.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Get batch name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get all jobs in the batch.
     *
     * @return array<int, Job>
     */
    public function jobs(): array
    {
        return $this->jobs;
    }

    /**
     * Get completed job count.
     */
    public function completedCount(): int
    {
        return count($this->completed);
    }

    /**
     * Get failed job count.
     */
    public function failedCount(): int
    {
        return count($this->failed);
    }

    /**
     * Get total job count.
     */
    public function totalCount(): int
    {
        return count($this->jobs);
    }
}

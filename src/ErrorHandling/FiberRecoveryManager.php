<?php

declare(strict_types=1);

namespace FiberFlow\ErrorHandling;

use FiberFlow\Metrics\MetricsCollector;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Manages Fiber crash recovery and retry logic.
 */
class FiberRecoveryManager
{
    /**
     * Maximum retry attempts for a crashed Fiber.
     */
    protected int $maxRetries;

    /**
     * Delay between retry attempts (in seconds).
     */
    protected int $retryDelay;

    /**
     * Failed jobs tracking.
     *
     * @var array<string, int>
     */
    protected array $failedJobs = [];

    /**
     * Create a new Fiber recovery manager instance.
     */
    public function __construct(
        protected ErrorHandler $errorHandler,
        protected ?MetricsCollector $metrics = null,
        ?int $maxRetries = null,
        ?int $retryDelay = null,
    ) {
        $this->maxRetries = $maxRetries ?? config('fiberflow.error_handling.max_retries', 3);
        $this->retryDelay = $retryDelay ?? config('fiberflow.error_handling.retry_delay', 1);
    }

    /**
     * Attempt to recover from a Fiber crash.
     *
     * @return bool True if the job should be retried, false otherwise
     */
    public function attemptRecovery(\Fiber $fiber, object $job, Throwable $exception): bool
    {
        $jobId = $this->getJobIdentifier($job);
        $attempts = $this->getAttempts($jobId);

        // Log the crash
        $this->errorHandler->handleFiberCrash($fiber, $job, $exception);

        // Check if we should retry
        if ($attempts >= $this->maxRetries) {
            Log::warning('Job exceeded max retry attempts', [
                'job_id' => $jobId,
                'job_class' => get_class($job),
                'attempts' => $attempts,
                'max_retries' => $this->maxRetries,
            ]);

            $this->markJobAsFailed($jobId);

            return false;
        }

        // Increment attempts
        $this->incrementAttempts($jobId);

        Log::info('Retrying crashed job', [
            'job_id' => $jobId,
            'job_class' => get_class($job),
            'attempt' => $attempts + 1,
            'max_retries' => $this->maxRetries,
        ]);

        $this->metrics?->recordJobRetried();

        // Delay before retry
        if ($this->retryDelay > 0) {
            sleep($this->retryDelay);
        }

        return true;
    }

    /**
     * Get the number of attempts for a job.
     */
    public function getAttempts(string $jobId): int
    {
        return $this->failedJobs[$jobId] ?? 0;
    }

    /**
     * Increment the attempt counter for a job.
     */
    protected function incrementAttempts(string $jobId): void
    {
        if (! isset($this->failedJobs[$jobId])) {
            $this->failedJobs[$jobId] = 0;
        }

        $this->failedJobs[$jobId]++;
    }

    /**
     * Mark a job as permanently failed.
     */
    protected function markJobAsFailed(string $jobId): void
    {
        unset($this->failedJobs[$jobId]);
        $this->metrics?->recordJobFailed();
    }

    /**
     * Clear the failure record for a job (on success).
     */
    public function clearFailures(string $jobId): void
    {
        unset($this->failedJobs[$jobId]);
    }

    /**
     * Get a unique identifier for a job.
     */
    protected function getJobIdentifier(object $job): string
    {
        // Try to get job ID from common properties
        if (property_exists($job, 'id')) {
            return (string) $job->id;
        }

        if (property_exists($job, 'uuid')) {
            return (string) $job->uuid;
        }

        if (method_exists($job, 'getJobId')) {
            return (string) $job->getJobId();
        }

        // Fallback to object hash
        return spl_object_hash($job);
    }

    /**
     * Get all failed jobs.
     *
     * @return array<string, int>
     */
    public function getFailedJobs(): array
    {
        return $this->failedJobs;
    }

    /**
     * Reset all failure tracking.
     */
    public function reset(): void
    {
        $this->failedJobs = [];
    }
}

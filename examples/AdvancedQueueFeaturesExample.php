<?php

declare(strict_types=1);

namespace FiberFlow\Examples;

use FiberFlow\Queue\DelayedJobQueue;
use FiberFlow\Queue\JobBatch;
use FiberFlow\Queue\PriorityQueue;
use FiberFlow\Queue\QueueConcurrencyManager;
use FiberFlow\Queue\RateLimiter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Example demonstrating advanced queue features in FiberFlow.
 *
 * This example shows:
 * - Priority queues
 * - Delayed jobs
 * - Job batching
 * - Rate limiting
 * - Queue-specific concurrency limits
 */

// ============================================================================
// Example 1: Priority Queues
// ============================================================================

class CriticalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        logger()->info('Processing critical job');
    }
}

class NormalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        logger()->info('Processing normal job');
    }
}

function priorityQueueExample(): void
{
    $queue = new PriorityQueue;

    // Add jobs with different priorities
    $queue->push(new NormalJob, priority: 1);
    $queue->push(new CriticalJob, priority: 10); // Higher priority
    $queue->push(new NormalJob, priority: 1);

    // Process jobs - CriticalJob will be processed first
    while (! $queue->isEmpty()) {
        $job = $queue->pop();
        $job->handle();
    }
}

// ============================================================================
// Example 2: Delayed Jobs
// ============================================================================

class ScheduledNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $message,
        protected int $userId,
    ) {}

    public function handle(): void
    {
        logger()->info("Sending notification to user {$this->userId}: {$this->message}");
    }
}

function delayedJobExample(): void
{
    $queue = new DelayedJobQueue;

    // Schedule jobs for future execution
    $queue->push(new ScheduledNotificationJob('Reminder: Meeting in 1 hour', 123), delay: 3600);
    $queue->push(new ScheduledNotificationJob('Daily digest', 123), delay: 86400);

    // Check for ready jobs periodically
    while (true) {
        $readyJobs = $queue->getReadyJobs();

        foreach ($readyJobs as $job) {
            $job->handle();
        }

        if ($queue->isEmpty()) {
            break;
        }

        // Wait until next job is ready
        $waitTime = $queue->getTimeUntilNext();
        if ($waitTime !== null) {
            sleep((int) ceil($waitTime));
        }
    }
}

// ============================================================================
// Example 3: Job Batching
// ============================================================================

class DataProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $recordId,
    ) {}

    public function handle(): void
    {
        logger()->info("Processing record {$this->recordId}");
    }
}

function jobBatchExample(): void
{
    $batch = new JobBatch('import-batch-123', 'Import User Data');

    // Add jobs to batch
    for ($i = 1; $i <= 100; $i++) {
        $batch->add(new DataProcessingJob($i));
    }

    // Register callbacks
    $batch
        ->then(function ($batch) {
            logger()->info("Batch {$batch->name()} completed successfully!");
            logger()->info("Processed {$batch->completedCount()} jobs");
        })
        ->catch(function ($batch) {
            logger()->error("Batch {$batch->name()} failed!");
            logger()->error("Failed jobs: {$batch->failedCount()}");
        })
        ->finally(function ($batch) {
            logger()->info("Batch {$batch->name()} finished");
            logger()->info("Progress: {$batch->progress()}%");
        });

    // Process jobs and track progress
    foreach ($batch->jobs() as $index => $job) {
        try {
            $job->handle();
            $batch->markCompleted("job-{$index}");
        } catch (\Throwable $e) {
            $batch->markFailed("job-{$index}");
        }
    }
}

// ============================================================================
// Example 4: Rate Limiting
// ============================================================================

class ApiCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $endpoint,
    ) {}

    public function handle(): void
    {
        logger()->info("Calling API: {$this->endpoint}");
    }
}

function rateLimitingExample(): void
{
    // Limit to 10 requests per second with burst capacity of 20
    $limiter = new RateLimiter(maxTokens: 20, refillRate: 10.0);

    $jobs = [
        new ApiCallJob('/users'),
        new ApiCallJob('/posts'),
        new ApiCallJob('/comments'),
        // ... more jobs
    ];

    foreach ($jobs as $job) {
        // Wait for rate limit
        $limiter->wait(tokens: 1);

        // Process job
        $job->handle();

        logger()->info("Tokens remaining: {$limiter->getTokens()}");
    }
}

// ============================================================================
// Example 5: Queue-Specific Concurrency Limits
// ============================================================================

function queueConcurrencyExample(): void
{
    $manager = new QueueConcurrencyManager(defaultLimit: 50);

    // Set different limits for different queues
    $manager->setLimit('high-priority', 100);
    $manager->setLimit('low-priority', 10);
    $manager->setLimit('api-calls', 5);

    // Process jobs respecting queue limits
    $queues = ['high-priority', 'low-priority', 'api-calls'];

    foreach ($queues as $queueName) {
        if ($manager->canProcess($queueName)) {
            $manager->increment($queueName);

            logger()->info("Processing job from {$queueName}");
            logger()->info("Active: {$manager->getActiveCount($queueName)}/{$manager->getLimit($queueName)}");

            // ... process job ...

            $manager->decrement($queueName);
        } else {
            logger()->info("Queue {$queueName} at capacity");
        }
    }
}

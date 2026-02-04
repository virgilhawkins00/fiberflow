<?php

declare(strict_types=1);

namespace FiberFlow\Loop;

use FiberFlow\Coroutine\SandboxManager;
use FiberFlow\ErrorHandling\ErrorHandler;
use FiberFlow\ErrorHandling\FiberRecoveryManager;
use FiberFlow\Metrics\MetricsCollector;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Revolt\EventLoop;

class FiberLoop
{
    /**
     * Indicates if the worker should stop.
     */
    protected bool $shouldQuit = false;

    /**
     * Statistics for the current run.
     *
     * @var array<string, int>
     */
    protected array $stats = [
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'start_time' => 0,
    ];

    /**
     * Create a new Fiber loop instance.
     */
    public function __construct(
        protected ConcurrencyManager $concurrency,
        protected SandboxManager $sandbox,
        protected ?ErrorHandler $errorHandler = null,
        protected ?FiberRecoveryManager $recoveryManager = null,
        protected ?MetricsCollector $metrics = null
    ) {
        $this->errorHandler = $errorHandler ?? new ErrorHandler($metrics);
        $this->recoveryManager = $recoveryManager ?? new FiberRecoveryManager($this->errorHandler, $metrics);
    }

    /**
     * Start the Fiber-based queue worker.
     *
     * @param array<string, mixed> $options
     */
    public function run(string $connection, string $queue, array $options = []): void
    {
        $this->stats['start_time'] = time();
        $this->registerSignalHandlers();

        $workerOptions = $this->createWorkerOptions($options);

        EventLoop::repeat(0.05, function (string $callbackId) use ($connection, $queue, $workerOptions) {
            if ($this->shouldQuit) {
                EventLoop::cancel($callbackId);
                $this->shutdown();
                return;
            }

            $this->processNextJob($connection, $queue, $workerOptions);
        });

        EventLoop::run();
    }

    /**
     * Process the next job from the queue.
     */
    protected function processNextJob(string $connection, string $queue, WorkerOptions $options): void
    {
        if ($this->concurrency->isFull()) {
            return;
        }

        $job = $this->getNextJob($connection, $queue);

        if ($job === null) {
            usleep((int) ($options->sleep * 1000000));
            return;
        }

        try {
            $this->concurrency->spawn(function () use ($job, $options): void {
                $this->runJobInFiber($job, $options);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to spawn Fiber for job', [
                'job' => $job->getName(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Run a job within a Fiber with sandboxed container.
     */
    protected function runJobInFiber(Job $job, WorkerOptions $options): void
    {
        $sandbox = $this->sandbox->createSandbox();
        $fiber = \Fiber::getCurrent();

        try {
            $job->fire();
            $this->stats['jobs_processed']++;
            $this->metrics?->recordJobCompleted(0.0);

            // Clear failure tracking on success
            if ($this->recoveryManager) {
                $jobId = $this->getJobIdentifier($job);
                $this->recoveryManager->clearFailures($jobId);
            }
        } catch (\Throwable $e) {
            $this->stats['jobs_failed']++;

            // Attempt recovery if we have a Fiber context
            if ($fiber && $this->recoveryManager) {
                $shouldRetry = $this->recoveryManager->attemptRecovery($fiber, $job, $e);

                if ($shouldRetry) {
                    // Re-queue the job for retry
                    try {
                        $job->release($this->recoveryManager->getAttempts($this->getJobIdentifier($job)));
                    } catch (\Throwable $releaseException) {
                        Log::error('Failed to release job for retry', [
                            'job' => $job->getName(),
                            'exception' => $releaseException->getMessage(),
                        ]);
                    }
                } else {
                    // Max retries exceeded, mark as failed
                    $this->handleJobException($job, $e, $options);
                }
            } else {
                // No recovery manager, handle normally
                $this->handleJobException($job, $e, $options);
            }
        } finally {
            $this->sandbox->destroySandbox();
        }
    }

    /**
     * Get the next job from the queue.
     */
    protected function getNextJob(string $connection, string $queue): ?Job
    {
        try {
            return Queue::connection($connection)->pop($queue);
        } catch (\Throwable $e) {
            Log::error('Failed to pop job from queue', [
                'connection' => $connection,
                'queue' => $queue,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Handle an exception that occurred while processing a job.
     */
    protected function handleJobException(Job $job, \Throwable $e, WorkerOptions $options): void
    {
        // Use error handler if available
        if ($this->errorHandler) {
            $this->errorHandler->handle($e);
        }

        try {
            $job->fail($e);
        } catch (\Throwable $failException) {
            Log::error('Failed to mark job as failed', [
                'job' => $job->getName(),
                'exception' => $failException->getMessage(),
            ]);
        }

        Log::error('Job failed', [
            'job' => $job->getName(),
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * Get a unique identifier for a job.
     */
    protected function getJobIdentifier(Job $job): string
    {
        return $job->getJobId() ?? spl_object_hash($job);
    }

    /**
     * Create worker options from the given array.
     *
     * @param array<string, mixed> $options
     */
    protected function createWorkerOptions(array $options): WorkerOptions
    {
        return new WorkerOptions(
            name: 'fiberflow',
            backoff: (int) ($options['backoff'] ?? 0),
            memory: (int) ($options['memory'] ?? 128),
            timeout: (int) ($options['timeout'] ?? 60),
            sleep: (int) ($options['sleep'] ?? 3),
            maxTries: (int) ($options['tries'] ?? 1),
            force: (bool) ($options['force'] ?? false),
            stopWhenEmpty: (bool) ($options['stop_when_empty'] ?? false),
            maxJobs: (int) ($options['max_jobs'] ?? 0),
            maxTime: (int) ($options['max_time'] ?? 0),
            rest: (int) ($options['rest'] ?? 0)
        );
    }

    /**
     * Register signal handlers for graceful shutdown.
     */
    protected function registerSignalHandlers(): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);

            pcntl_signal(SIGTERM, function (): void {
                $this->initiateGracefulShutdown('SIGTERM');
            });

            pcntl_signal(SIGINT, function (): void {
                $this->initiateGracefulShutdown('SIGINT');
            });
        }
    }

    /**
     * Initiate graceful shutdown process.
     */
    protected function initiateGracefulShutdown(string $signal): void
    {
        $this->shouldQuit = true;

        Log::info("Received {$signal}, initiating graceful shutdown...", [
            'active_fibers' => $this->concurrency->getActiveCount(),
            'jobs_processed' => $this->stats['jobs_processed'],
            'jobs_failed' => $this->stats['jobs_failed'],
        ]);
    }

    /**
     * Shutdown the worker gracefully.
     */
    protected function shutdown(): void
    {
        Log::info('FiberFlow worker shutting down gracefully...');

        // Wait for active Fibers to complete (with timeout)
        $timeout = config('fiberflow.shutdown_timeout', 30);
        $startTime = time();

        while ($this->concurrency->getActiveCount() > 0) {
            if (time() - $startTime > $timeout) {
                Log::warning('Shutdown timeout reached, forcing termination', [
                    'remaining_fibers' => $this->concurrency->getActiveCount(),
                ]);
                break;
            }

            // Give Fibers time to complete
            usleep(100000); // 100ms
        }

        Log::info('FiberFlow worker stopped', [
            'total_jobs_processed' => $this->stats['jobs_processed'],
            'total_jobs_failed' => $this->stats['jobs_failed'],
            'uptime' => time() - $this->stats['start_time'],
        ]);
    }
}


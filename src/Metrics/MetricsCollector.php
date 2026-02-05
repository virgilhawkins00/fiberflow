<?php

declare(strict_types=1);

namespace FiberFlow\Metrics;

/**
 * Collects and aggregates metrics for FiberFlow workers.
 */
class MetricsCollector
{
    /**
     * Metrics data storage.
     *
     * @var array<string, mixed>
     */
    protected array $metrics = [];

    /**
     * Start time of the worker.
     */
    protected float $startTime;

    /**
     * Create a new metrics collector instance.
     */
    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->initializeMetrics();
    }

    /**
     * Initialize default metrics.
     */
    protected function initializeMetrics(): void
    {
        $this->metrics = [
            'jobs' => [
                'processed' => 0,
                'failed' => 0,
                'retried' => 0,
                'total' => 0,
            ],
            'fibers' => [
                'active' => 0,
                'spawned' => 0,
                'completed' => 0,
                'failed' => 0,
            ],
            'memory' => [
                'current' => 0,
                'peak' => 0,
                'limit' => ini_get('memory_limit'),
            ],
            'performance' => [
                'throughput' => 0.0,
                'avg_job_time' => 0.0,
                'uptime' => 0,
            ],
            'queue' => [
                'pending' => 0,
                'processing' => 0,
            ],
        ];
    }

    /**
     * Increment a metric counter.
     */
    public function increment(string $category, string $metric, int $amount = 1): void
    {
        if (! isset($this->metrics[$category][$metric])) {
            $this->metrics[$category][$metric] = 0;
        }

        $this->metrics[$category][$metric] += $amount;
    }

    /**
     * Set a metric value.
     */
    public function set(string $category, string $metric, mixed $value): void
    {
        if (! isset($this->metrics[$category])) {
            $this->metrics[$category] = [];
        }

        $this->metrics[$category][$metric] = $value;
    }

    /**
     * Get a metric value.
     */
    public function get(string $category, string $metric, mixed $default = null): mixed
    {
        return $this->metrics[$category][$metric] ?? $default;
    }

    /**
     * Update memory metrics.
     */
    public function updateMemoryMetrics(): void
    {
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);

        $this->set('memory', 'current', $current);
        $this->set('memory', 'peak', $peak);
    }

    /**
     * Update performance metrics.
     */
    public function updatePerformanceMetrics(): void
    {
        $uptime = microtime(true) - $this->startTime;
        $this->set('performance', 'uptime', $uptime);

        $totalJobs = $this->get('jobs', 'total', 0);
        if ($uptime > 0) {
            $throughput = $totalJobs / $uptime;
            $this->set('performance', 'throughput', $throughput);
        }
    }

    /**
     * Record a job completion.
     */
    public function recordJobCompleted(float $duration): void
    {
        $this->increment('jobs', 'processed');
        $this->increment('jobs', 'total');

        // Update average job time
        $totalJobs = $this->get('jobs', 'total', 1);
        $currentAvg = $this->get('performance', 'avg_job_time', 0.0);
        $newAvg = (($currentAvg * ($totalJobs - 1)) + $duration) / $totalJobs;
        $this->set('performance', 'avg_job_time', $newAvg);
    }

    /**
     * Record a job failure.
     */
    public function recordJobFailed(): void
    {
        $this->increment('jobs', 'failed');
        $this->increment('jobs', 'total');
    }

    /**
     * Record a job retry.
     */
    public function recordJobRetried(): void
    {
        $this->increment('jobs', 'retried');
    }

    /**
     * Record a Fiber spawn.
     */
    public function recordFiberSpawned(): void
    {
        $this->increment('fibers', 'spawned');
        $this->increment('fibers', 'active');
    }

    /**
     * Record a Fiber completion.
     */
    public function recordFiberCompleted(): void
    {
        $this->increment('fibers', 'completed');
        $this->increment('fibers', 'active', -1);
    }

    /**
     * Record a Fiber failure.
     */
    public function recordFiberFailed(): void
    {
        $this->increment('fibers', 'failed');
        $this->increment('fibers', 'active', -1);
    }

    /**
     * Get all metrics.
     *
     * @return array<string, mixed>
     */
    public function getAllMetrics(): array
    {
        $this->updateMemoryMetrics();
        $this->updatePerformanceMetrics();

        return $this->metrics;
    }

    /**
     * Get a snapshot of current metrics.
     *
     * @return array<string, mixed>
     */
    public function getSnapshot(): array
    {
        return [
            'timestamp' => time(),
            'metrics' => $this->getAllMetrics(),
        ];
    }

    /**
     * Reset all metrics.
     */
    public function reset(): void
    {
        $this->startTime = microtime(true);
        $this->initializeMetrics();
    }
}

<?php

declare(strict_types=1);

namespace FiberFlow\Console;

use FiberFlow\Metrics\MetricsCollector;

/**
 * Renders a terminal dashboard for FiberFlow metrics.
 */
class DashboardRenderer
{
    /**
     * Create a new dashboard renderer instance.
     */
    public function __construct(
        protected MetricsCollector $metrics,
        protected ?DashboardController $controller = null,
    ) {}

    /**
     * Render the dashboard.
     */
    public function render(): string
    {
        $metrics = $this->metrics->getAllMetrics();

        $output = $this->clearScreen();
        $output .= $this->renderHeader();
        $output .= $this->renderJobMetrics($metrics['jobs']);
        $output .= $this->renderFiberMetrics($metrics['fibers']);
        $output .= $this->renderMemoryMetrics($metrics['memory']);
        $output .= $this->renderPerformanceMetrics($metrics['performance']);
        $output .= $this->renderFooter();

        return $output;
    }

    /**
     * Clear the terminal screen.
     */
    protected function clearScreen(): string
    {
        return "\033[2J\033[H";
    }

    /**
     * Render the header.
     */
    protected function renderHeader(): string
    {
        $state = $this->controller?->getStateDisplay() ?? '🟢 RUNNING';

        $header = <<<HEADER
╔══════════════════════════════════════════════════════════════════════════════╗
║                        🚀 FiberFlow Dashboard 🚀                             ║
║                              Status: {$this->pad($state, 15)}                           ║
╚══════════════════════════════════════════════════════════════════════════════╝

HEADER;

        return $header;
    }

    /**
     * Render job metrics.
     *
     * @param array<string, mixed> $jobs
     */
    protected function renderJobMetrics(array $jobs): string
    {
        $processed = $jobs['processed'] ?? 0;
        $failed = $jobs['failed'] ?? 0;
        $retried = $jobs['retried'] ?? 0;
        $total = $jobs['total'] ?? 0;

        $successRate = $total > 0 ? round(($processed / $total) * 100, 1) : 0;

        return <<<JOBS
┌─ Jobs ────────────────────────────────────────────────────────────────────────┐
│  Total Processed: {$this->pad($processed, 10)}  Failed: {$this->pad($failed, 10)}  Retried: {$this->pad($retried, 10)}  │
│  Success Rate:    {$this->pad($successRate.'%', 10)}                                              │
└───────────────────────────────────────────────────────────────────────────────┘

JOBS;
    }

    /**
     * Render Fiber metrics.
     *
     * @param array<string, mixed> $fibers
     */
    protected function renderFiberMetrics(array $fibers): string
    {
        $active = $fibers['active'] ?? 0;
        $spawned = $fibers['spawned'] ?? 0;
        $completed = $fibers['completed'] ?? 0;
        $failed = $fibers['failed'] ?? 0;

        $activeBar = $this->renderProgressBar($active, 50, 30);

        return <<<FIBERS
┌─ Fibers ──────────────────────────────────────────────────────────────────────┐
│  Active:    {$this->pad($active, 5)}  {$activeBar}                                    │
│  Spawned:   {$this->pad($spawned, 10)}  Completed: {$this->pad($completed, 10)}  Failed: {$this->pad($failed, 10)}  │
└───────────────────────────────────────────────────────────────────────────────┘

FIBERS;
    }

    /**
     * Render memory metrics.
     *
     * @param array<string, mixed> $memory
     */
    protected function renderMemoryMetrics(array $memory): string
    {
        $current = $this->formatBytes($memory['current'] ?? 0);
        $peak = $this->formatBytes($memory['peak'] ?? 0);
        $limit = $memory['limit'] ?? 'unlimited';

        return <<<MEMORY
┌─ Memory ──────────────────────────────────────────────────────────────────────┐
│  Current: {$this->pad($current, 12)}  Peak: {$this->pad($peak, 12)}  Limit: {$this->pad($limit, 12)}  │
└───────────────────────────────────────────────────────────────────────────────┘

MEMORY;
    }

    /**
     * Render performance metrics.
     *
     * @param array<string, mixed> $performance
     */
    protected function renderPerformanceMetrics(array $performance): string
    {
        $throughput = round($performance['throughput'] ?? 0, 2);
        $avgJobTime = round(($performance['avg_job_time'] ?? 0) * 1000, 2); // Convert to ms
        $uptime = $this->formatDuration($performance['uptime'] ?? 0);

        return <<<PERFORMANCE
┌─ Performance ─────────────────────────────────────────────────────────────────┐
│  Throughput:  {$this->pad($throughput.' jobs/s', 15)}  Avg Job Time: {$this->pad($avgJobTime.' ms', 15)}  │
│  Uptime:      {$this->pad($uptime, 15)}                                              │
└───────────────────────────────────────────────────────────────────────────────┘

PERFORMANCE;
    }

    /**
     * Render the footer.
     */
    protected function renderFooter(): string
    {
        if ($this->controller !== null) {
            return "\n".$this->controller->getHelpText()."\n";
        }

        return "\nPress Ctrl+C to stop the worker\n";
    }

    /**
     * Render a progress bar.
     */
    protected function renderProgressBar(int $value, int $max, int $width): string
    {
        $percentage = $max > 0 ? min(($value / $max) * 100, 100) : 0;
        $filled = (int) round(($percentage / 100) * $width);
        $empty = $width - $filled;

        return '['.str_repeat('█', $filled).str_repeat('░', $empty).']';
    }

    /**
     * Pad a value to a specific width.
     */
    protected function pad(mixed $value, int $width): string
    {
        return str_pad((string) $value, $width, ' ', STR_PAD_RIGHT);
    }

    /**
     * Format bytes to human-readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }

    /**
     * Format duration to human-readable format.
     */
    protected function formatDuration(float $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = floor($seconds % 60);

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        } else {
            return sprintf('%ds', $secs);
        }
    }
}

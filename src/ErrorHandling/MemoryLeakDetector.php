<?php

declare(strict_types=1);

namespace FiberFlow\ErrorHandling;

use Illuminate\Support\Facades\Log;

/**
 * Detects memory leaks in FiberFlow workers.
 */
class MemoryLeakDetector
{
    /**
     * Memory usage samples.
     *
     * @var array<int, int>
     */
    protected array $samples = [];

    /**
     * Maximum number of samples to keep.
     */
    protected int $maxSamples;

    /**
     * Memory leak threshold (in bytes).
     */
    protected int $leakThreshold;

    /**
     * Sample interval (in seconds).
     */
    protected int $sampleInterval;

    /**
     * Last sample time.
     */
    protected float $lastSampleTime = 0;

    /**
     * Create a new memory leak detector instance.
     */
    public function __construct(
        ?int $maxSamples = null,
        ?int $leakThreshold = null,
        ?int $sampleInterval = null,
    ) {
        $this->maxSamples = $maxSamples ?? config('fiberflow.memory_leak.max_samples', 100);
        $this->leakThreshold = $leakThreshold ?? config('fiberflow.memory_leak.threshold', 10 * 1024 * 1024); // 10MB
        $this->sampleInterval = $sampleInterval ?? config('fiberflow.memory_leak.sample_interval', 5); // 5 seconds
    }

    /**
     * Take a memory sample.
     */
    public function sample(): void
    {
        $now = microtime(true);

        // Check if enough time has passed since last sample
        if ($now - $this->lastSampleTime < $this->sampleInterval) {
            return;
        }

        $this->lastSampleTime = $now;
        $memoryUsage = memory_get_usage(true);

        $this->samples[] = $memoryUsage;

        // Keep only the last N samples
        if (count($this->samples) > $this->maxSamples) {
            array_shift($this->samples);
        }

        // Check for memory leak
        if ($this->detectLeak()) {
            $this->reportLeak();
        }
    }

    /**
     * Detect if there's a memory leak.
     */
    protected function detectLeak(): bool
    {
        if (count($this->samples) < 10) {
            return false; // Not enough samples
        }

        // Calculate trend using linear regression
        $n = count($this->samples);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        foreach ($this->samples as $x => $y) {
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        // Calculate slope (memory growth rate)
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);

        // If slope is positive and exceeds threshold, we have a leak
        return $slope > 0 && ($slope * $n) > $this->leakThreshold;
    }

    /**
     * Report a detected memory leak.
     */
    protected function reportLeak(): void
    {
        $currentMemory = end($this->samples);
        $firstMemory = reset($this->samples);
        $growth = $currentMemory - $firstMemory;

        Log::warning('Memory leak detected', [
            'current_memory' => $this->formatBytes($currentMemory),
            'initial_memory' => $this->formatBytes($firstMemory),
            'growth' => $this->formatBytes($growth),
            'samples' => count($this->samples),
            'threshold' => $this->formatBytes($this->leakThreshold),
        ]);
    }

    /**
     * Get current memory usage.
     */
    public function getCurrentMemory(): int
    {
        return memory_get_usage(true);
    }

    /**
     * Get peak memory usage.
     */
    public function getPeakMemory(): int
    {
        return memory_get_peak_usage(true);
    }

    /**
     * Get all samples.
     *
     * @return array<int, int>
     */
    public function getSamples(): array
    {
        return $this->samples;
    }

    /**
     * Reset the detector.
     */
    public function reset(): void
    {
        $this->samples = [];
        $this->lastSampleTime = 0;
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
}

<?php

declare(strict_types=1);

/**
 * FiberFlow HTTP Benchmark.
 *
 * Compares performance of FiberFlow vs traditional synchronous approach
 * for I/O-heavy workloads (HTTP requests).
 *
 * Usage:
 *   php benchmarks/HttpBenchmark.php
 */

require __DIR__.'/../vendor/autoload.php';

use FiberFlow\Http\AsyncHttpClient;
use Revolt\EventLoop;

class HttpBenchmark
{
    protected int $numRequests = 100;

    protected string $testUrl = 'https://httpbin.org/delay/0.2';

    public function run(): void
    {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════╗\n";
        echo "║         FiberFlow HTTP Performance Benchmark            ║\n";
        echo "╚══════════════════════════════════════════════════════════╝\n";
        echo "\n";

        echo "Configuration:\n";
        echo "  - Requests: {$this->numRequests}\n";
        echo "  - URL: {$this->testUrl}\n";
        echo "  - Each request takes ~0.5 seconds\n";
        echo "\n";

        $this->benchmarkTraditional();
        $this->benchmarkFiberFlow();
        $this->showComparison();
    }

    protected function benchmarkTraditional(): void
    {
        echo "Running Traditional Synchronous Approach...\n";

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        for ($i = 0; $i < $this->numRequests; $i++) {
            file_get_contents($this->testUrl);
        }

        $duration = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $startMemory;

        $this->traditionalResults = [
            'duration' => $duration,
            'memory' => $memoryUsed,
            'throughput' => $this->numRequests / $duration,
        ];

        echo '  ✓ Completed in '.round($duration, 2)."s\n";
        echo '  ✓ Memory: '.$this->formatBytes($memoryUsed)."\n";
        echo '  ✓ Throughput: '.round($this->traditionalResults['throughput'], 2)." req/s\n";
        echo "\n";
    }

    protected function benchmarkFiberFlow(): void
    {
        echo "Running FiberFlow Concurrent Approach...\n";

        $client = new AsyncHttpClient;

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $completed = 0;
        $pending = $this->numRequests;

        // Queue all requests to the event loop
        for ($i = 0; $i < $this->numRequests; $i++) {
            EventLoop::queue(function () use ($client, &$completed) {
                try {
                    $client->get($this->testUrl);
                } catch (\Throwable $e) {
                    echo "  ⚠ Request failed: {$e->getMessage()}\n";
                } finally {
                    $completed++;
                }
            });
        }

        // Run the event loop until all requests complete
        $checkInterval = EventLoop::repeat(0.1, function (string $callbackId) use (&$completed) {
            if ($completed >= $this->numRequests) {
                EventLoop::cancel($callbackId);
            }
        });

        EventLoop::run();

        $duration = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $startMemory;

        $this->fiberFlowResults = [
            'duration' => $duration,
            'memory' => $memoryUsed,
            'throughput' => $this->numRequests / $duration,
        ];

        echo '  ✓ Completed in '.round($duration, 2)."s\n";
        echo '  ✓ Memory: '.$this->formatBytes($memoryUsed)."\n";
        echo '  ✓ Throughput: '.round($this->fiberFlowResults['throughput'], 2)." req/s\n";
        echo "\n";
    }

    protected function showComparison(): void
    {
        echo "╔══════════════════════════════════════════════════════════╗\n";
        echo "║                    Performance Gains                     ║\n";
        echo "╚══════════════════════════════════════════════════════════╝\n";
        echo "\n";

        $speedup = $this->traditionalResults['duration'] / $this->fiberFlowResults['duration'];
        $throughputGain = $this->fiberFlowResults['throughput'] / $this->traditionalResults['throughput'];
        $memoryRatio = $this->traditionalResults['memory'] / max($this->fiberFlowResults['memory'], 1);

        echo "Speed Improvement:\n";
        echo '  Traditional: '.round($this->traditionalResults['duration'], 2)."s\n";
        echo '  FiberFlow:   '.round($this->fiberFlowResults['duration'], 2)."s\n";
        echo '  → '.round($speedup, 1)."x FASTER 🚀\n";
        echo "\n";

        echo "Throughput Improvement:\n";
        echo '  Traditional: '.round($this->traditionalResults['throughput'], 2)." req/s\n";
        echo '  FiberFlow:   '.round($this->fiberFlowResults['throughput'], 2)." req/s\n";
        echo '  → '.round($throughputGain, 1)."x HIGHER 📈\n";
        echo "\n";

        echo "Memory Efficiency:\n";
        echo '  Traditional: '.$this->formatBytes($this->traditionalResults['memory'])."\n";
        echo '  FiberFlow:   '.$this->formatBytes($this->fiberFlowResults['memory'])."\n";
        if ($memoryRatio > 1) {
            echo '  → '.round($memoryRatio, 1)."x LESS MEMORY 💾\n";
        } else {
            echo "  → Similar memory usage\n";
        }
        echo "\n";
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }

    protected array $traditionalResults = [];

    protected array $fiberFlowResults = [];
}

// Run the benchmark
$benchmark = new HttpBenchmark;
$benchmark->run();

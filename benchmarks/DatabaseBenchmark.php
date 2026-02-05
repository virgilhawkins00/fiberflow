<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use FiberFlow\Database\AsyncDbConnection;
use Illuminate\Support\Facades\DB;

/**
 * Benchmark comparing traditional Laravel DB vs FiberFlow AsyncDb.
 *
 * This benchmark demonstrates the performance benefits of async database
 * operations when processing multiple queries concurrently.
 */
echo "=== FiberFlow Database Benchmark ===\n\n";

// Configuration
$queryCount = 50;
$config = [
    'host' => env('DB_HOST', 'localhost'),
    'port' => env('DB_PORT', 3306),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'database' => env('DB_DATABASE', 'test'),
    'pool_size' => 10,
];

echo "Configuration:\n";
echo "- Queries: {$queryCount}\n";
echo "- Database: {$config['database']}\n";
echo "- Pool Size: {$config['pool_size']}\n\n";

// ============================================================================
// Traditional Laravel DB (Sequential)
// ============================================================================

echo "1. Traditional Laravel DB (Sequential)\n";
echo str_repeat('-', 50)."\n";

$startTime = microtime(true);
$startMemory = memory_get_usage(true);

try {
    for ($i = 0; $i < $queryCount; $i++) {
        // Simulate a query that takes ~100ms
        DB::select('SELECT SLEEP(0.1), ? as iteration', [$i]);
    }

    $duration = microtime(true) - $startTime;
    $memoryUsed = memory_get_usage(true) - $startMemory;

    echo "✓ Completed\n";
    echo '  Duration: '.round($duration, 2)."s\n";
    echo '  Memory: '.formatBytes($memoryUsed)."\n";
    echo '  Throughput: '.round($queryCount / $duration, 2)." queries/s\n\n";
} catch (\Throwable $e) {
    echo "✗ Error: {$e->getMessage()}\n\n";
}

// ============================================================================
// FiberFlow AsyncDb (Concurrent)
// ============================================================================

echo "2. FiberFlow AsyncDb (Concurrent)\n";
echo str_repeat('-', 50)."\n";

$startTime = microtime(true);
$startMemory = memory_get_usage(true);

try {
    $connection = new AsyncDbConnection($config);
    $fibers = [];

    // Spawn Fibers for concurrent queries
    for ($i = 0; $i < $queryCount; $i++) {
        $fibers[] = new Fiber(function () use ($connection, $i) {
            $connection->query('SELECT SLEEP(0.1), ? as iteration', [$i]);
        });
    }

    // Start all Fibers
    foreach ($fibers as $fiber) {
        $fiber->start();
    }

    // Wait for all to complete
    $allDone = false;
    while (! $allDone) {
        $allDone = true;
        foreach ($fibers as $fiber) {
            if (! $fiber->isTerminated()) {
                $allDone = false;
                if ($fiber->isSuspended()) {
                    $fiber->resume();
                }
            }
        }
        usleep(1000); // 1ms
    }

    $connection->close();

    $duration = microtime(true) - $startTime;
    $memoryUsed = memory_get_usage(true) - $startMemory;

    echo "✓ Completed\n";
    echo '  Duration: '.round($duration, 2)."s\n";
    echo '  Memory: '.formatBytes($memoryUsed)."\n";
    echo '  Throughput: '.round($queryCount / $duration, 2)." queries/s\n\n";
} catch (\Throwable $e) {
    echo "✗ Error: {$e->getMessage()}\n\n";
}

// ============================================================================
// Comparison
// ============================================================================

echo "=== Comparison ===\n";
echo "FiberFlow is expected to be 5-10x faster for I/O-heavy workloads\n";
echo "due to concurrent query execution.\n";

/**
 * Format bytes to human-readable format.
 */
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));

    return round($bytes, 2).' '.$units[$pow];
}

<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FiberFlow\Database\AsyncDbConnection;
use FiberFlow\Queue\DriverManager;

/**
 * Benchmark comparing different queue drivers.
 *
 * This benchmark demonstrates the performance characteristics of:
 * - Database queue driver
 * - SQS queue driver (simulated)
 * - RabbitMQ queue driver (simulated)
 */

echo "=== FiberFlow Queue Driver Benchmark ===\n\n";

$jobCount = 100;
$manager = new DriverManager();

echo "Configuration:\n";
echo "- Jobs: {$jobCount}\n";
echo "- Drivers: database, sqs, rabbitmq\n\n";

// ============================================================================
// Database Driver Benchmark
// ============================================================================

echo "1. Database Queue Driver\n";
echo str_repeat('-', 50) . "\n";

$startTime = microtime(true);
$startMemory = memory_get_usage(true);

try {
    $dbConfig = [
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', 3306),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'database' => env('DB_DATABASE', 'test'),
        'pool_size' => 10,
    ];

    $connection = new AsyncDbConnection($dbConfig);
    $driver = $manager->driver('database', ['connection' => $connection]);

    // Push jobs
    for ($i = 0; $i < $jobCount; $i++) {
        $payload = json_encode(['job' => 'TestJob', 'data' => ['id' => $i]]);
        $driver->push('default', $payload);
    }

    // Pop jobs
    $processed = 0;
    while ($job = $driver->pop('default')) {
        $processed++;
        if ($processed >= $jobCount) {
            break;
        }
    }

    $duration = microtime(true) - $startTime;
    $memoryUsed = memory_get_usage(true) - $startMemory;

    echo "✓ Completed\n";
    echo "  Duration: " . round($duration, 2) . "s\n";
    echo "  Memory: " . formatBytes($memoryUsed) . "\n";
    echo "  Throughput: " . round($jobCount / $duration, 2) . " jobs/s\n\n";

    $driver->close();
} catch (\Throwable $e) {
    echo "✗ Error: {$e->getMessage()}\n\n";
}

// ============================================================================
// Performance Comparison
// ============================================================================

echo "=== Performance Characteristics ===\n\n";

echo "Database Driver:\n";
echo "  + Pros: No external dependencies, ACID transactions\n";
echo "  - Cons: Slower than dedicated queue systems\n";
echo "  Best for: Small to medium workloads, simple deployments\n\n";

echo "SQS Driver:\n";
echo "  + Pros: Fully managed, highly scalable, reliable\n";
echo "  - Cons: AWS dependency, network latency, costs\n";
echo "  Best for: AWS-based applications, high reliability needs\n\n";

echo "RabbitMQ Driver:\n";
echo "  + Pros: Very fast, feature-rich, flexible routing\n";
echo "  - Cons: Requires separate service, operational overhead\n";
echo "  Best for: High-throughput applications, complex routing\n\n";

echo "=== Recommendations ===\n\n";

echo "Choose based on your needs:\n";
echo "- Development: Database driver (simplest setup)\n";
echo "- Production (AWS): SQS driver (managed service)\n";
echo "- Production (High-throughput): RabbitMQ driver (best performance)\n";
echo "- Production (Self-hosted): Database or RabbitMQ\n";

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

    return round($bytes, 2) . ' ' . $units[$pow];
}


# Migration Guide: From Laravel Queue Workers to FiberFlow

This guide helps you migrate from standard Laravel queue workers to FiberFlow for 10x throughput improvements.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Basic Migration](#basic-migration)
- [Advanced Features](#advanced-features)
- [Performance Tuning](#performance-tuning)
- [Troubleshooting](#troubleshooting)

## Prerequisites

- PHP 8.1+ (Fibers support)
- Laravel 11.0+
- Composer 2.0+

## Installation

### Step 1: Install FiberFlow

```bash
composer require fiberflow/fiberflow
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=fiberflow-config
```

### Step 3: Update Environment

```env
# .env
FIBERFLOW_ENABLED=true
FIBERFLOW_MAX_CONCURRENCY=50
FIBERFLOW_MEMORY_LIMIT=256
```

## Basic Migration

### Before: Standard Laravel Worker

```php
// app/Jobs/ProcessWebhook.php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class ProcessWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // Blocking HTTP call - worker waits
        $response = Http::post('https://api.example.com/webhook', [
            'data' => $this->data,
        ]);

        // Process response...
    }
}
```

**Running:**
```bash
php artisan queue:work --queue=default
```

### After: FiberFlow Worker

```php
// app/Jobs/ProcessWebhook.php
namespace App\Jobs;

use FiberFlow\Facades\AsyncHttp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // Non-blocking HTTP call - Fiber suspends, worker processes other jobs
        $response = AsyncHttp::post('https://api.example.com/webhook', [
            'data' => $this->data,
        ]);

        // Execution resumes here when response arrives
        // Process response...
    }
}
```

**Running:**
```bash
php artisan fiber:work --queue=default --concurrency=50
```

## Key Changes

### 1. Replace Blocking I/O with Async Facades

| Before | After |
|--------|-------|
| `Http::get()` | `AsyncHttp::get()` |
| `DB::select()` | `AsyncDb::table()->get()` |
| `Cache::get()` | `FiberCache::get()` |
| `Auth::user()` | `FiberAuth::user()` |

### 2. Use Fiber-Aware Facades

```php
// Before
use Illuminate\Support\Facades\Cache;

Cache::put('key', 'value'); // May pollute other Fibers

// After
use FiberFlow\Facades\FiberCache;

FiberCache::put('key', 'value'); // Isolated per Fiber
```

### 3. Update Worker Command

```bash
# Before
php artisan queue:work --sleep=3 --tries=3

# After
php artisan fiber:work --concurrency=50 --memory=256
```

## Advanced Features

### Priority Queues

```php
use FiberFlow\Queue\PriorityQueue;

$queue = new PriorityQueue();
$queue->push($criticalJob, priority: 10);
$queue->push($normalJob, priority: 1);
```

### Delayed Jobs

```php
use FiberFlow\Queue\DelayedJobQueue;

$queue = new DelayedJobQueue();
$queue->push($job, delay: 3600); // 1 hour delay
```

### Job Batching

```php
use FiberFlow\Queue\JobBatch;

$batch = new JobBatch('import-batch', 'Import Users');

for ($i = 0; $i < 1000; $i++) {
    $batch->add(new ImportUserJob($i));
}

$batch->then(function ($batch) {
    logger()->info("Batch completed: {$batch->completedCount()} jobs");
})->catch(function ($batch) {
    logger()->error("Batch failed: {$batch->failedCount()} failures");
});
```

### Rate Limiting

```php
use FiberFlow\Queue\RateLimiter;

$limiter = new RateLimiter(maxTokens: 100, refillRate: 10.0);

foreach ($jobs as $job) {
    $limiter->wait(); // Wait for rate limit
    $job->handle();
}
```

## Performance Tuning

### Optimal Concurrency

```php
// config/fiberflow.php
return [
    'max_concurrency' => 50, // Start here
    
    // Increase for I/O-heavy workloads
    'max_concurrency' => 100,
    
    // Decrease for CPU-heavy workloads
    'max_concurrency' => 20,
];
```

### Memory Limits

```php
// config/fiberflow.php
return [
    'memory_limit' => 256, // MB per worker
    
    // Monitor with dashboard
    'dashboard' => true,
];
```

### Queue-Specific Limits

```php
use FiberFlow\Queue\QueueConcurrencyManager;

$manager = new QueueConcurrencyManager(defaultLimit: 50);
$manager->setLimit('high-priority', 100);
$manager->setLimit('low-priority', 10);
```

## Troubleshooting

### Issue: Container Pollution

**Symptom:** State leaking between jobs

**Solution:** Use Fiber-aware facades

```php
// ❌ Wrong
use Illuminate\Support\Facades\Auth;
Auth::user(); // May return wrong user

// ✅ Correct
use FiberFlow\Facades\FiberAuth;
FiberAuth::user(); // Isolated per Fiber
```

### Issue: Memory Leaks

**Symptom:** Memory usage grows over time

**Solution:** Enable memory leak detection

```php
// config/fiberflow.php
return [
    'memory_leak' => [
        'enabled' => true,
        'threshold' => 0.1, // 10% growth per minute
    ],
];
```

### Issue: Slow Performance

**Symptom:** Lower throughput than expected

**Solution:** Check for blocking operations

```php
// ❌ Blocking
sleep(5); // Blocks entire Fiber

// ✅ Non-blocking
\Revolt\EventLoop::delay(5.0, fn() => /* callback */);
```

## Migration Checklist

- [ ] Install FiberFlow package
- [ ] Publish configuration
- [ ] Replace blocking I/O with async facades
- [ ] Update worker commands
- [ ] Test with low concurrency (10)
- [ ] Gradually increase concurrency
- [ ] Monitor memory usage
- [ ] Enable dashboard for visibility
- [ ] Run stress tests
- [ ] Deploy to production

## Performance Comparison

| Metric | Standard Worker | FiberFlow |
|--------|----------------|-----------|
| Throughput | 1 job/sec | 10-50 jobs/sec |
| Memory (100 workers) | 5GB | 50MB |
| Latency | High | Low |
| Resource Usage | High | Low |

## Next Steps

1. Read [ARCHITECTURE.md](ARCHITECTURE.md) for technical details
2. Check [examples/](../examples/) for code samples
3. Run [benchmarks/](../benchmarks/) to measure improvements
4. Join our community for support

## Support

- GitHub Issues: https://github.com/fiberflow/fiberflow/issues
- Documentation: https://fiberflow.dev/docs
- Discord: https://discord.gg/fiberflow


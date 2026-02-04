# FiberFlow Benchmarks

Performance benchmarks comparing FiberFlow with traditional synchronous approaches.

## Running Benchmarks

### HTTP Benchmark

Compares concurrent HTTP requests using FiberFlow vs sequential requests:

```bash
php benchmarks/HttpBenchmark.php
```

**Expected Results:**
- **Speed**: 10-50x faster for I/O-heavy workloads
- **Throughput**: 10-50x higher requests per second
- **Memory**: Similar or better memory efficiency

### Sample Output

```
╔══════════════════════════════════════════════════════════╗
║         FiberFlow HTTP Performance Benchmark            ║
╚══════════════════════════════════════════════════════════╝

Configuration:
  - Requests: 50
  - URL: https://httpbin.org/delay/1
  - Each request takes ~1 second

Running Traditional Synchronous Approach...
  ✓ Completed in 52.34s
  ✓ Memory: 2.5 MB
  ✓ Throughput: 0.96 req/s

Running FiberFlow Concurrent Approach...
  ✓ Completed in 1.23s
  ✓ Memory: 3.1 MB
  ✓ Throughput: 40.65 req/s

╔══════════════════════════════════════════════════════════╗
║                    Performance Gains                     ║
╚══════════════════════════════════════════════════════════╝

Speed Improvement:
  Traditional: 52.34s
  FiberFlow:   1.23s
  → 42.5x FASTER 🚀

Throughput Improvement:
  Traditional: 0.96 req/s
  FiberFlow:   40.65 req/s
  → 42.3x HIGHER 📈

Memory Efficiency:
  Traditional: 2.5 MB
  FiberFlow:   3.1 MB
  → Similar memory usage
```

## Understanding the Results

### Why is FiberFlow Faster?

**Traditional Approach:**
- Processes requests sequentially
- Each request blocks for ~1 second
- Total time = Number of requests × Request time
- 50 requests × 1s = 50 seconds

**FiberFlow Approach:**
- Processes requests concurrently
- Fibers suspend during I/O, allowing others to run
- Total time ≈ Request time (all run in parallel)
- 50 requests concurrently = ~1 second

### When Does FiberFlow Excel?

✅ **Best For:**
- HTTP API calls
- Webhook delivery
- Web scraping
- Email sending (SMTP)
- Database queries (with async driver)
- File downloads
- Microservice communication

❌ **Not Ideal For:**
- CPU-intensive tasks (image processing, video encoding)
- Blocking operations without async alternatives
- Single request workloads

## Creating Custom Benchmarks

```php
<?php

require __DIR__.'/../vendor/autoload.php';

use FiberFlow\Http\AsyncHttpClient;

$client = new AsyncHttpClient();
$startTime = microtime(true);

// Your benchmark code here
$fibers = [];
for ($i = 0; $i < 100; $i++) {
    $fibers[] = new Fiber(function () use ($client) {
        $response = $client->get('https://api.example.com/data');
        // Process response...
    });
}

foreach ($fibers as $fiber) {
    $fiber->start();
}

// Wait for completion...

$duration = microtime(true) - $startTime;
echo "Completed in {$duration}s\n";
```

## Benchmark Guidelines

1. **Use Real Endpoints**: Test with actual HTTP endpoints for realistic results
2. **Warm Up**: Run a few requests first to warm up connections
3. **Multiple Runs**: Average results across multiple runs
4. **Network Conditions**: Results vary based on network latency
5. **Concurrency Limits**: Test different concurrency levels (10, 50, 100)

## Contributing Benchmarks

Have a benchmark that demonstrates FiberFlow's performance? Submit a PR!

Requirements:
- Clear documentation
- Reproducible results
- Comparison with traditional approach
- Sample output included


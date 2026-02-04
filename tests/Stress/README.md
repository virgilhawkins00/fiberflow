# FiberFlow Stress Tests

This directory contains stress tests for validating FiberFlow's performance and stability under high load.

## Running Stress Tests

### Prerequisites

```bash
# Install dependencies
composer install

# Ensure PHP 8.1+ with Fibers support
php --version
```

### Run All Tests

```bash
php tests/Stress/StressTest.php
```

### Expected Output

```
=== FiberFlow Stress Test ===

Configuration:
- Total jobs: 10,000
- Max concurrency: 100
- Memory limit: 256M

Test 1: Memory Stability
--------------------------------------------------
  Progress: 10.0% - Memory: 12.45MB
  Progress: 20.0% - Memory: 14.23MB
  ...
  Progress: 100.0% - Memory: 18.67MB

✓ Test 1 Completed
  Duration: 5.23s
  Jobs processed: 10,000
  Throughput: 1,912.05 jobs/s
  Start memory: 10.00MB
  Peak memory: 18.67MB
  Memory used: 8.67MB
  Memory per job: 0.89KB

Test 2: Concurrency Manager Stress
--------------------------------------------------
  Progress: 10.0% - Active: 100
  Progress: 20.0% - Active: 100
  ...

✓ Test 2 Completed
  Duration: 4.87s
  Jobs spawned: 10,000
  Jobs completed: 10,000
  Throughput: 2,053.39 jobs/s

Test 3: Error Handling at Scale
--------------------------------------------------
✓ Test 3 Completed
  Duration: 0.15s
  Successful: 900
  Failed: 100
  Error rate: 10.00%

=== Stress Test Summary ===

✓ All tests passed!
✓ Memory stable under load
✓ Concurrency limits enforced
✓ Error handling works at scale

FiberFlow is production-ready! 🚀
```

## Test Scenarios

### Test 1: Memory Stability

- **Purpose:** Validate memory usage remains stable under load
- **Jobs:** 10,000
- **Concurrency:** 100
- **Success Criteria:** 
  - Memory per job < 1KB
  - No memory leaks
  - Peak memory < 50MB

### Test 2: Concurrency Manager

- **Purpose:** Validate concurrency limits are enforced
- **Jobs:** 10,000
- **Max Concurrency:** 100
- **Success Criteria:**
  - Active count never exceeds limit
  - All jobs complete successfully
  - Throughput > 1,000 jobs/s

### Test 3: Error Handling

- **Purpose:** Validate error handling at scale
- **Jobs:** 1,000 (10% fail)
- **Success Criteria:**
  - Errors are caught and logged
  - Failed jobs don't crash worker
  - Success rate = 90%

## Performance Benchmarks

### Expected Performance

| Metric | Target | Typical |
|--------|--------|---------|
| Throughput | > 1,000 jobs/s | 1,500-2,500 jobs/s |
| Memory per job | < 1KB | 0.5-1KB |
| Peak memory | < 50MB | 15-25MB |
| Error handling | 100% caught | 100% |

### Comparison with Standard Workers

| Metric | Standard Worker | FiberFlow |
|--------|----------------|-----------|
| Throughput | 50-100 jobs/s | 1,500-2,500 jobs/s |
| Memory (100 workers) | 5GB | 20MB |
| Concurrency | 1 per worker | 100 per worker |

## Troubleshooting

### High Memory Usage

If memory usage is higher than expected:

1. Check for memory leaks in job code
2. Reduce concurrency limit
3. Enable memory leak detection
4. Profile with Xdebug

### Low Throughput

If throughput is lower than expected:

1. Check for blocking operations
2. Increase concurrency limit
3. Optimize I/O operations
4. Use async facades

### Test Failures

If tests fail:

1. Check PHP version (8.1+ required)
2. Verify Fibers are enabled
3. Check memory_limit in php.ini
4. Review error logs

## Custom Stress Tests

You can create custom stress tests by extending the base test:

```php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use FiberFlow\Loop\ConcurrencyManager;

$manager = new ConcurrencyManager(100);
$jobCount = 50000; // Custom job count

for ($i = 0; $i < $jobCount; $i++) {
    while ($manager->isFull()) {
        usleep(100);
    }

    $manager->spawn(function () use ($i) {
        // Your custom job logic
        usleep(rand(100, 1000));
    });
}

// Wait for completion
while ($manager->getActiveCount() > 0) {
    usleep(1000);
}

echo "Completed {$jobCount} jobs!\n";
```

## CI/CD Integration

Add stress tests to your CI pipeline:

```yaml
# .github/workflows/stress-test.yml
name: Stress Tests

on: [push, pull_request]

jobs:
  stress-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install
      - run: php tests/Stress/StressTest.php
```

## Support

For issues or questions:
- GitHub Issues: https://github.com/fiberflow/fiberflow/issues
- Documentation: https://fiberflow.dev/docs


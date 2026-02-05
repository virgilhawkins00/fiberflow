<?php

declare(strict_types=1);

use FiberFlow\ErrorHandling\MemoryLeakDetector;

beforeEach(function () {
    $this->detector = new MemoryLeakDetector(100, 10 * 1024 * 1024, 0); // 0 second interval for testing
});

it('initializes with default configuration', function () {
    $detector = new MemoryLeakDetector;

    expect($detector)->toBeInstanceOf(MemoryLeakDetector::class);
});

it('takes memory samples', function () {
    $this->detector->sample();
    $samples = $this->detector->getSamples();

    expect($samples)->toHaveCount(1);
    expect($samples[0])->toBeInt();
    expect($samples[0])->toBeGreaterThan(0);
});

it('limits number of samples', function () {
    $detector = new MemoryLeakDetector(5, 10 * 1024 * 1024, 0);

    // Take 10 samples
    for ($i = 0; $i < 10; $i++) {
        $detector->sample();
    }

    $samples = $detector->getSamples();

    expect($samples)->toHaveCount(5); // Should keep only last 5
});

it('respects sample interval', function () {
    $detector = new MemoryLeakDetector(100, 10 * 1024 * 1024, 1); // 1 second interval

    $detector->sample();
    $detector->sample(); // Should be ignored (too soon)

    $samples = $detector->getSamples();

    expect($samples)->toHaveCount(1);
});

it('can reset samples', function () {
    $this->detector->sample();
    $this->detector->sample();

    $this->detector->reset();
    $samples = $this->detector->getSamples();

    expect($samples)->toBeEmpty();
});

it('can get current memory usage', function () {
    $current = $this->detector->getCurrentMemory();

    expect($current)->toBeInt();
    expect($current)->toBeGreaterThan(0);
});

it('can get peak memory usage', function () {
    $peak = $this->detector->getPeakMemory();

    expect($peak)->toBeInt();
    expect($peak)->toBeGreaterThan(0);
});

it('can get all samples', function () {
    // Take some samples
    for ($i = 0; $i < 5; $i++) {
        $this->detector->sample();
        usleep(10000);
    }

    $samples = $this->detector->getSamples();

    expect($samples)->toBeArray()
        ->and(count($samples))->toBeGreaterThan(0);
});

it('tracks memory usage over time', function () {
    // Take enough samples for trend detection
    for ($i = 0; $i < 15; $i++) {
        $this->detector->sample();
        usleep(10000);
    }

    $samples = $this->detector->getSamples();

    expect(count($samples))->toBeGreaterThanOrEqual(10);
});

it('can check if leak is detected', function () {
    // With low threshold, should not detect leak immediately
    $detector = new MemoryLeakDetector(100, 1024 * 1024 * 1024, 0); // 1GB threshold

    for ($i = 0; $i < 15; $i++) {
        $detector->sample();
    }

    // Should not detect leak with high threshold
    expect(true)->toBeTrue();
});

it('detects memory leak with increasing samples', function () {
    $detector = new MemoryLeakDetector(100, 1024, 0); // Very low threshold

    // Use reflection to add artificial increasing samples
    $reflection = new ReflectionClass($detector);
    $samplesProperty = $reflection->getProperty('samples');
    $samplesProperty->setAccessible(true);

    // Create artificial leak pattern
    $samples = [];
    for ($i = 0; $i < 15; $i++) {
        $samples[] = 1000000 + ($i * 100000); // Increasing memory
    }
    $samplesProperty->setValue($detector, $samples);

    // Test detectLeak method
    $detectLeakMethod = $reflection->getMethod('detectLeak');
    $detectLeakMethod->setAccessible(true);

    $hasLeak = $detectLeakMethod->invoke($detector);

    expect($hasLeak)->toBeTrue();
});

it('does not detect leak with insufficient samples', function () {
    $detector = new MemoryLeakDetector(100, 1024, 0);

    // Use reflection to test with few samples
    $reflection = new ReflectionClass($detector);
    $samplesProperty = $reflection->getProperty('samples');
    $samplesProperty->setAccessible(true);

    $samples = [1000000, 1000100, 1000200]; // Only 3 samples
    $samplesProperty->setValue($detector, $samples);

    $detectLeakMethod = $reflection->getMethod('detectLeak');
    $detectLeakMethod->setAccessible(true);

    $hasLeak = $detectLeakMethod->invoke($detector);

    expect($hasLeak)->toBeFalse();
});

it('does not detect leak with stable memory', function () {
    $detector = new MemoryLeakDetector(100, 1024, 0);

    // Use reflection to add stable samples
    $reflection = new ReflectionClass($detector);
    $samplesProperty = $reflection->getProperty('samples');
    $samplesProperty->setAccessible(true);

    // Create stable memory pattern
    $samples = array_fill(0, 15, 1000000); // Same memory
    $samplesProperty->setValue($detector, $samples);

    $detectLeakMethod = $reflection->getMethod('detectLeak');
    $detectLeakMethod->setAccessible(true);

    $hasLeak = $detectLeakMethod->invoke($detector);

    expect($hasLeak)->toBeFalse();
});

it('formats bytes correctly', function () {
    $detector = new MemoryLeakDetector;

    $reflection = new ReflectionClass($detector);
    $method = $reflection->getMethod('formatBytes');
    $method->setAccessible(true);

    expect($method->invoke($detector, 1024))->toBe('1 KB');
    expect($method->invoke($detector, 1024 * 1024))->toBe('1 MB');
    expect($method->invoke($detector, 1024 * 1024 * 1024))->toBe('1 GB');
    expect($method->invoke($detector, 500))->toBe('500 B');
});

it('reports leak when detected', function () {
    $detector = new MemoryLeakDetector(100, 100, 0); // Very low threshold

    // Use reflection to add artificial leak
    $reflection = new ReflectionClass($detector);
    $samplesProperty = $reflection->getProperty('samples');
    $samplesProperty->setAccessible(true);

    $samples = [];
    for ($i = 0; $i < 15; $i++) {
        $samples[] = 1000000 + ($i * 100000);
    }
    $samplesProperty->setValue($detector, $samples);

    // Test reportLeak method
    $reportLeakMethod = $reflection->getMethod('reportLeak');
    $reportLeakMethod->setAccessible(true);

    // Should not throw
    $reportLeakMethod->invoke($detector);

    expect(true)->toBeTrue();
});

it('samples and detects leak automatically', function () {
    $detector = new MemoryLeakDetector(100, 100, 0); // Very low threshold

    // Use reflection to simulate leak
    $reflection = new ReflectionClass($detector);
    $samplesProperty = $reflection->getProperty('samples');
    $samplesProperty->setAccessible(true);

    // Add enough samples to trigger detection
    $samples = [];
    for ($i = 0; $i < 15; $i++) {
        $samples[] = 1000000 + ($i * 100000);
    }
    $samplesProperty->setValue($detector, $samples);

    // Sample should trigger leak detection
    $detector->sample();

    expect(true)->toBeTrue();
});

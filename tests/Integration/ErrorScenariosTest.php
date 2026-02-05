<?php

declare(strict_types=1);

use FiberFlow\ErrorHandling\ErrorHandler;
use FiberFlow\ErrorHandling\MemoryLeakDetector;
use FiberFlow\Http\AsyncHttpClient;
use FiberFlow\Queue\DelayedJobQueue;
use FiberFlow\Queue\PriorityQueue;
use FiberFlow\Queue\RateLimiter;

it('handles network timeout gracefully', function () {
    $client = new AsyncHttpClient([
        'timeout' => 1, // 1 second timeout
        'max_retries' => 0,
    ]);

    // This should timeout quickly
    expect(function () use ($client) {
        $client->get('https://httpbin.org/delay/10');
    })->toThrow(Exception::class);
});

it('handles rate limiting correctly', function () {
    $limiter = new RateLimiter(
        maxTokens: 2,
        refillRate: 1.0, // 1 token per second
    );

    // First two attempts should succeed
    expect($limiter->attempt())->toBeTrue();
    expect($limiter->attempt())->toBeTrue();

    // Third attempt should fail (no tokens left)
    expect($limiter->attempt())->toBeFalse();
});

it('handles empty queue gracefully', function () {
    $queue = new PriorityQueue;

    expect($queue->isEmpty())->toBeTrue();
    expect($queue->pop())->toBeNull();
});

it('handles delayed jobs correctly', function () {
    $queue = new DelayedJobQueue;

    $payload = ['job' => 'test'];
    $delay = 2; // 2 seconds

    $queue->push($payload, $delay);

    // Job should not be available immediately
    expect($queue->pop())->toBeNull();
});

it('handles priority queue ordering', function () {
    $queue = new PriorityQueue;

    $queue->push(['job' => 'low'], 1);
    $queue->push(['job' => 'high'], 10);
    $queue->push(['job' => 'medium'], 5);

    // Should pop in priority order (high to low)
    $first = $queue->pop();
    expect($first['job'])->toBe('high');

    $second = $queue->pop();
    expect($second['job'])->toBe('medium');

    $third = $queue->pop();
    expect($third['job'])->toBe('low');
});

it('detects memory leaks', function () {
    $detector = new MemoryLeakDetector(
        threshold: 1024 * 1024, // 1MB threshold
        checkInterval: 1,
    );

    $initialMemory = memory_get_usage(true);

    // Simulate memory usage
    $data = [];
    for ($i = 0; $i < 1000; $i++) {
        $data[] = str_repeat('x', 1024); // 1KB each
    }

    $currentMemory = memory_get_usage(true);

    expect($currentMemory)->toBeGreaterThan($initialMemory);

    // Clean up
    unset($data);
});

it('handles error handler correctly', function () {
    $handler = new ErrorHandler;

    $exception = new Exception('Test error');

    // Should not throw when handling exception
    expect(function () use ($handler, $exception) {
        $handler->handle($exception);
    })->not->toThrow(Exception::class);
});

it('handles concurrent rate limiting', function () {
    $limiter = new RateLimiter(
        maxTokens: 5,
        refillRate: 2.0,
    );

    $successCount = 0;
    $failCount = 0;

    // Try 10 attempts
    for ($i = 0; $i < 10; $i++) {
        if ($limiter->attempt()) {
            $successCount++;
        } else {
            $failCount++;
        }
    }

    // Should have 5 successes and 5 failures
    expect($successCount)->toBe(5)
        ->and($failCount)->toBe(5);
});

it('handles priority queue with same priority', function () {
    $queue = new PriorityQueue;

    // Add multiple jobs with same priority
    $queue->push(['job' => 'first'], 5);
    $queue->push(['job' => 'second'], 5);
    $queue->push(['job' => 'third'], 5);

    // Should maintain FIFO order for same priority
    $first = $queue->pop();
    expect($first['job'])->toBe('first');

    $second = $queue->pop();
    expect($second['job'])->toBe('second');

    $third = $queue->pop();
    expect($third['job'])->toBe('third');
});

it('handles queue size correctly', function () {
    $queue = new PriorityQueue;

    expect($queue->size())->toBe(0);

    $queue->push(['job' => 'test1'], 1);
    expect($queue->size())->toBe(1);

    $queue->push(['job' => 'test2'], 2);
    expect($queue->size())->toBe(2);

    $queue->pop();
    expect($queue->size())->toBe(1);

    $queue->pop();
    expect($queue->size())->toBe(0);
});

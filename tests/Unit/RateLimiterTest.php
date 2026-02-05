<?php

declare(strict_types=1);

use FiberFlow\Queue\RateLimiter;

test('it starts with max tokens', function () {
    $limiter = new RateLimiter(maxTokens: 10, refillRate: 1.0);

    expect($limiter->getTokens())->toBe(10.0);
    expect($limiter->getMaxTokens())->toBe(10);
});

test('it consumes tokens on attempt', function () {
    $limiter = new RateLimiter(maxTokens: 10, refillRate: 1.0);

    expect($limiter->attempt(3))->toBeTrue();
    expect($limiter->getTokens())->toBeGreaterThanOrEqual(6.9);
    expect($limiter->getTokens())->toBeLessThanOrEqual(7.1);
});

test('it rejects when insufficient tokens', function () {
    $limiter = new RateLimiter(maxTokens: 5, refillRate: 1.0);

    expect($limiter->attempt(3))->toBeTrue();
    expect($limiter->attempt(3))->toBeFalse(); // Only 2 tokens left
});

test('it refills tokens over time', function () {
    $limiter = new RateLimiter(maxTokens: 10, refillRate: 10.0); // 10 tokens/second

    $limiter->attempt(10); // Consume all tokens
    expect($limiter->getTokens())->toBeLessThan(0.1); // Nearly 0

    usleep(100000); // Wait 0.1 seconds

    // Should have ~1 token refilled (10 tokens/sec * 0.1 sec)
    expect($limiter->getTokens())->toBeGreaterThan(0.5);
});

test('it does not exceed max tokens', function () {
    $limiter = new RateLimiter(maxTokens: 10, refillRate: 100.0);

    sleep(1); // Wait for refill

    expect($limiter->getTokens())->toBe(10.0); // Should not exceed max
});

test('it calculates wait time correctly', function () {
    $limiter = new RateLimiter(maxTokens: 10, refillRate: 10.0);

    $limiter->attempt(10); // Consume all tokens

    $waitTime = $limiter->getWaitTime(5);
    expect($waitTime)->toBeGreaterThan(0.4); // ~0.5 seconds for 5 tokens at 10/sec
    expect($waitTime)->toBeLessThan(0.6);
});

test('it returns zero wait time when tokens available', function () {
    $limiter = new RateLimiter(maxTokens: 10, refillRate: 1.0);

    expect($limiter->getWaitTime(5))->toBe(0.0);
});

test('it resets to max tokens', function () {
    $limiter = new RateLimiter(maxTokens: 10, refillRate: 1.0);

    $limiter->attempt(8);
    expect($limiter->getTokens())->toBeGreaterThanOrEqual(1.9);
    expect($limiter->getTokens())->toBeLessThanOrEqual(2.1);

    $limiter->reset();
    expect($limiter->getTokens())->toBe(10.0);
});

test('it handles fractional tokens', function () {
    $limiter = new RateLimiter(maxTokens: 10, refillRate: 0.5); // 0.5 tokens/second

    $limiter->attempt(10);
    usleep(500000); // Wait 0.5 seconds

    // Should have ~0.25 tokens refilled
    expect($limiter->getTokens())->toBeGreaterThan(0.2);
    expect($limiter->getTokens())->toBeLessThan(0.3);
});

test('it gets refill rate', function () {
    $limiter = new RateLimiter(maxTokens: 10, refillRate: 5.5);

    expect($limiter->getRefillRate())->toBe(5.5);
});

test('it waits until tokens are available', function () {
    $limiter = new RateLimiter(maxTokens: 5, refillRate: 10.0);

    // Consume all tokens
    $limiter->attempt(5);

    // This should wait and then succeed
    $startTime = microtime(true);
    $limiter->wait(1);
    $elapsed = microtime(true) - $startTime;

    // Should have waited approximately 0.1 seconds (1 token / 10 tokens per second)
    expect($elapsed)->toBeGreaterThan(0.05);
    expect($limiter->getTokens())->toBeLessThan(5);
});

test('it waits for multiple tokens', function () {
    $limiter = new RateLimiter(maxTokens: 10, refillRate: 10.0);

    // Consume all tokens
    $limiter->attempt(10);

    // Wait for 3 tokens
    $startTime = microtime(true);
    $limiter->wait(3);
    $elapsed = microtime(true) - $startTime;

    // Should have waited approximately 0.3 seconds (3 tokens / 10 tokens per second)
    expect($elapsed)->toBeGreaterThan(0.2);
});

test('it does not wait when tokens already available', function () {
    $limiter = new RateLimiter(maxTokens: 10, refillRate: 1.0);

    $startTime = microtime(true);
    $limiter->wait(5);
    $elapsed = microtime(true) - $startTime;

    // Should complete almost immediately
    expect($elapsed)->toBeLessThan(0.01);
});

test('it handles wait with single token', function () {
    $limiter = new RateLimiter(maxTokens: 1, refillRate: 10.0);

    $limiter->attempt(1);

    $startTime = microtime(true);
    $limiter->wait(1);
    $elapsed = microtime(true) - $startTime;

    expect($elapsed)->toBeGreaterThan(0.05);
});

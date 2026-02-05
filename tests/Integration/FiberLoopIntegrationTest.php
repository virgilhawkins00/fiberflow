<?php

declare(strict_types=1);

namespace Tests\Integration;

use FiberFlow\Loop\FiberLoop;
use FiberFlow\Loop\ConcurrencyManager;
use FiberFlow\Coroutine\SandboxManager;
use Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class)->group('integration', 'fiberloop');

test('it can create FiberLoop instance', function () {
    $concurrency = new ConcurrencyManager(maxConcurrency: 10);
    $sandbox = new SandboxManager($this->app);
    $loop = new FiberLoop($concurrency, $sandbox);

    expect($loop)->toBeInstanceOf(FiberLoop::class);
});

test('it can get initial stats', function () {
    $concurrency = new ConcurrencyManager(maxConcurrency: 10);
    $sandbox = new SandboxManager($this->app);
    $loop = new FiberLoop($concurrency, $sandbox);

    $stats = $loop->getStats();

    expect($stats)->toBeArray();
    expect($stats)->toHaveKey('jobs_processed');
    expect($stats)->toHaveKey('jobs_failed');
    expect($stats)->toHaveKey('start_time');
    expect($stats['jobs_processed'])->toBe(0);
    expect($stats['jobs_failed'])->toBe(0);
});

test('it can pause and resume', function () {
    $concurrency = new ConcurrencyManager(maxConcurrency: 10);
    $sandbox = new SandboxManager($this->app);
    $loop = new FiberLoop($concurrency, $sandbox);

    expect($loop->isPaused())->toBeFalse();

    // Pause
    $loop->pause();
    expect($loop->isPaused())->toBeTrue();

    // Resume
    $loop->resume();
    expect($loop->isPaused())->toBeFalse();
});

test('it can stop gracefully', function () {
    $concurrency = new ConcurrencyManager(maxConcurrency: 10);
    $sandbox = new SandboxManager($this->app);
    $loop = new FiberLoop($concurrency, $sandbox);

    // Stop the loop
    $loop->stop();

    // Verify shouldQuit is set to true
    $reflection = new \ReflectionClass($loop);
    $property = $reflection->getProperty('shouldQuit');
    $property->setAccessible(true);

    expect($property->getValue($loop))->toBeTrue();
});

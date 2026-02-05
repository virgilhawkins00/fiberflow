<?php

declare(strict_types=1);

use FiberFlow\Coroutine\SandboxManager;
use FiberFlow\ErrorHandling\ErrorHandler;
use FiberFlow\ErrorHandling\FiberRecoveryManager;
use FiberFlow\Loop\ConcurrencyManager;
use FiberFlow\Loop\FiberLoop;
use FiberFlow\Metrics\MetricsCollector;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->concurrency = new ConcurrencyManager(10);
    $this->sandbox = new SandboxManager(app());
    $this->metrics = new MetricsCollector;
    $this->errorHandler = new ErrorHandler($this->metrics);
    $this->recovery = new FiberRecoveryManager($this->errorHandler, $this->metrics);

    $this->loop = new FiberLoop(
        $this->concurrency,
        $this->sandbox,
        $this->errorHandler,
        $this->recovery,
        $this->metrics,
    );
});

it('initializes with dependencies', function () {
    expect($this->loop)->toBeInstanceOf(FiberLoop::class);
});

it('creates default dependencies when not provided', function () {
    $loop = new FiberLoop(
        $this->concurrency,
        $this->sandbox,
    );

    expect($loop)->toBeInstanceOf(FiberLoop::class);
});

it('can access protected stats property', function () {
    $reflection = new ReflectionClass($this->loop);
    $statsProperty = $reflection->getProperty('stats');
    $statsProperty->setAccessible(true);
    $stats = $statsProperty->getValue($this->loop);

    expect($stats)->toBeArray()
        ->and($stats)->toHaveKeys(['jobs_processed', 'jobs_failed', 'start_time']);
});

it('gets next job from queue', function () {
    $mockJob = Mockery::mock(Job::class);

    Queue::shouldReceive('connection')
        ->with('default')
        ->andReturnSelf();

    Queue::shouldReceive('pop')
        ->with('default')
        ->andReturn($mockJob);

    // Use reflection to call protected method
    $method = new ReflectionMethod(FiberLoop::class, 'getNextJob');
    $method->setAccessible(true);

    $job = $method->invoke($this->loop, 'default', 'default');

    expect($job)->toBe($mockJob);
});

it('returns null when queue is empty', function () {
    Queue::shouldReceive('connection')
        ->with('default')
        ->andReturnSelf();

    Queue::shouldReceive('pop')
        ->with('default')
        ->andReturn(null);

    // Use reflection to call protected method
    $method = new ReflectionMethod(FiberLoop::class, 'getNextJob');
    $method->setAccessible(true);

    $job = $method->invoke($this->loop, 'default', 'default');

    expect($job)->toBeNull();
});

it('handles queue pop exception', function () {
    Queue::shouldReceive('connection')
        ->with('default')
        ->andThrow(new Exception('Queue error'));

    // Use reflection to call protected method
    $method = new ReflectionMethod(FiberLoop::class, 'getNextJob');
    $method->setAccessible(true);

    $job = $method->invoke($this->loop, 'default', 'default');

    expect($job)->toBeNull();
});

it('initializes with default error handler and recovery manager', function () {
    $loop = new FiberLoop($this->concurrency, $this->sandbox);

    expect($loop)->toBeInstanceOf(FiberLoop::class);
});

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

it('creates worker options with default values', function () {
    $reflection = new ReflectionClass($this->loop);
    $method = $reflection->getMethod('createWorkerOptions');
    $method->setAccessible(true);

    $options = $method->invoke($this->loop, []);

    expect($options)->toBeInstanceOf(\Illuminate\Queue\WorkerOptions::class);
});

it('creates worker options with custom values', function () {
    $reflection = new ReflectionClass($this->loop);
    $method = $reflection->getMethod('createWorkerOptions');
    $method->setAccessible(true);

    $customOptions = [
        'backoff' => 5,
        'memory' => 256,
        'timeout' => 120,
        'sleep' => 5,
        'tries' => 3,
        'force' => true,
        'stop_when_empty' => true,
        'max_jobs' => 100,
        'max_time' => 3600,
        'rest' => 10,
    ];

    $options = $method->invoke($this->loop, $customOptions);

    expect($options)->toBeInstanceOf(\Illuminate\Queue\WorkerOptions::class);
});

it('gets job identifier from job id', function () {
    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn('job-123');

    $reflection = new ReflectionClass($this->loop);
    $method = $reflection->getMethod('getJobIdentifier');
    $method->setAccessible(true);

    $identifier = $method->invoke($this->loop, $mockJob);

    expect($identifier)->toBe('job-123');
});

it('gets job identifier from object hash when no job id', function () {
    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn(null);

    $reflection = new ReflectionClass($this->loop);
    $method = $reflection->getMethod('getJobIdentifier');
    $method->setAccessible(true);

    $identifier = $method->invoke($this->loop, $mockJob);

    expect($identifier)->toBeString();
    expect(strlen($identifier))->toBeGreaterThan(0);
});

it('handles job exception with error handler', function () {
    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getName')->andReturn('TestJob');
    $mockJob->shouldReceive('fail')->once();

    $exception = new Exception('Test exception');

    $reflection = new ReflectionClass($this->loop);
    $method = $reflection->getMethod('handleJobException');
    $method->setAccessible(true);

    $workerOptions = new \Illuminate\Queue\WorkerOptions;

    $method->invoke($this->loop, $mockJob, $exception, $workerOptions);

    expect(true)->toBeTrue();
});

it('handles job exception when fail throws exception', function () {
    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getName')->andReturn('TestJob');
    $mockJob->shouldReceive('fail')->andThrow(new Exception('Fail error'));

    $exception = new Exception('Test exception');

    $reflection = new ReflectionClass($this->loop);
    $method = $reflection->getMethod('handleJobException');
    $method->setAccessible(true);

    $workerOptions = new \Illuminate\Queue\WorkerOptions;

    // Should not throw exception
    $method->invoke($this->loop, $mockJob, $exception, $workerOptions);

    expect(true)->toBeTrue();
});

it('initiates graceful shutdown', function () {
    $reflection = new ReflectionClass($this->loop);
    $method = $reflection->getMethod('initiateGracefulShutdown');
    $method->setAccessible(true);

    $method->invoke($this->loop, 'SIGTERM');

    $shouldQuitProperty = $reflection->getProperty('shouldQuit');
    $shouldQuitProperty->setAccessible(true);

    expect($shouldQuitProperty->getValue($this->loop))->toBeTrue();
});

it('has registerSignalHandlers method', function () {
    $reflection = new ReflectionClass($this->loop);
    expect($reflection->hasMethod('registerSignalHandlers'))->toBeTrue();
});

it('has shutdown method', function () {
    $reflection = new ReflectionClass($this->loop);
    expect($reflection->hasMethod('shutdown'))->toBeTrue();
});

it('has processNextJob method', function () {
    $reflection = new ReflectionClass($this->loop);
    expect($reflection->hasMethod('processNextJob'))->toBeTrue();
});

it('has runJobInFiber method', function () {
    $reflection = new ReflectionClass($this->loop);
    expect($reflection->hasMethod('runJobInFiber'))->toBeTrue();
});

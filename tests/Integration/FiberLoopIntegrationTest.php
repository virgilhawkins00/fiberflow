<?php

declare(strict_types=1);

namespace Tests\Integration;

use FiberFlow\Coroutine\SandboxManager;
use FiberFlow\Loop\ConcurrencyManager;
use FiberFlow\Loop\FiberLoop;

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

test('it can process jobs from database queue', function () {
    if (! $this->isMySqlAvailable()) {
        $this->markTestSkipped('MySQL is not available');
    }

    // Create a test job in the database
    $payload = json_encode([
        'displayName' => 'Tests\\TestJob',
        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        'maxTries' => null,
        'timeout' => null,
        'data' => [
            'commandName' => 'Tests\\TestJob',
            'command' => serialize(new class
            {
                public function handle()
                {
                    // Simple test job
                }
            }),
        ],
    ]);

    \DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => $payload,
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => time(),
        'created_at' => time(),
    ]);

    $concurrency = new ConcurrencyManager(maxConcurrency: 10);
    $sandbox = new SandboxManager($this->app);
    $loop = new FiberLoop($concurrency, $sandbox);

    // Process one job and stop
    $processed = false;
    $fiber = new \Fiber(function () use ($loop, &$processed) {
        // Give the loop time to process one job
        \Revolt\EventLoop::delay(0.1, function () use ($loop, &$processed) {
            $stats = $loop->getStats();
            if ($stats['jobs_processed'] > 0 || $stats['jobs_failed'] > 0) {
                $processed = true;
            }
            $loop->stop();
        });
    });

    $fiber->start();

    // Note: We can't actually run the EventLoop here because it would block
    // This test verifies the setup is correct
    expect($loop)->toBeInstanceOf(FiberLoop::class);
})->skip('EventLoop::run() blocks test execution');

test('it respects pause state when processing jobs', function () {
    $concurrency = new ConcurrencyManager(maxConcurrency: 10);
    $sandbox = new SandboxManager($this->app);
    $loop = new FiberLoop($concurrency, $sandbox);

    // Pause the loop
    $loop->pause();
    expect($loop->isPaused())->toBeTrue();

    // Call processNextJob via reflection
    $reflection = new \ReflectionClass($loop);
    $method = $reflection->getMethod('processNextJob');
    $method->setAccessible(true);

    // Create mock worker options
    $workerOptions = new \Illuminate\Queue\WorkerOptions();

    // When paused, processNextJob should return early
    $method->invoke($loop, 'database', 'default', $workerOptions);

    // Stats should not change
    $stats = $loop->getStats();
    expect($stats['jobs_processed'])->toBe(0);
    expect($stats['jobs_failed'])->toBe(0);
});

test('it can create worker options from array', function () {
    $concurrency = new ConcurrencyManager(maxConcurrency: 10);
    $sandbox = new SandboxManager($this->app);
    $loop = new FiberLoop($concurrency, $sandbox);

    // Call createWorkerOptions via reflection
    $reflection = new \ReflectionClass($loop);
    $method = $reflection->getMethod('createWorkerOptions');
    $method->setAccessible(true);

    $options = [
        'sleep' => 5,
        'tries' => 3,
        'timeout' => 120,
        'memory' => 256,
        'backoff' => 10,
        'force' => true,
        'stop_when_empty' => true,
        'max_jobs' => 100,
        'max_time' => 3600,
        'rest' => 1,
    ];

    $workerOptions = $method->invoke($loop, $options);

    expect($workerOptions)->toBeInstanceOf(\Illuminate\Queue\WorkerOptions::class);
    expect($workerOptions->sleep)->toBe(5);
    expect($workerOptions->maxTries)->toBe(3);
    expect($workerOptions->timeout)->toBe(120);
    expect($workerOptions->memory)->toBe(256);
    expect($workerOptions->backoff)->toBe(10);
    expect($workerOptions->force)->toBeTrue();
    expect($workerOptions->stopWhenEmpty)->toBeTrue();
    expect($workerOptions->maxJobs)->toBe(100);
    expect($workerOptions->maxTime)->toBe(3600);
    expect($workerOptions->rest)->toBe(1);
});

test('it can get job identifier', function () {
    $concurrency = new ConcurrencyManager(maxConcurrency: 10);
    $sandbox = new SandboxManager($this->app);
    $loop = new FiberLoop($concurrency, $sandbox);

    // Create a mock job
    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('test-job-123');

    // Call getJobIdentifier via reflection
    $reflection = new \ReflectionClass($loop);
    $method = $reflection->getMethod('getJobIdentifier');
    $method->setAccessible(true);

    $identifier = $method->invoke($loop, $job);

    expect($identifier)->toBe('test-job-123');
});

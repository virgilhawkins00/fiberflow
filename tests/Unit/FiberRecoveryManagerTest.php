<?php

declare(strict_types=1);

use FiberFlow\ErrorHandling\ErrorHandler;
use FiberFlow\ErrorHandling\FiberRecoveryManager;
use FiberFlow\Metrics\MetricsCollector;
use Illuminate\Queue\Jobs\Job;

beforeEach(function () {
    $this->metrics = new MetricsCollector;
    $this->errorHandler = new ErrorHandler($this->metrics);
    $this->recovery = new FiberRecoveryManager($this->errorHandler, $this->metrics, 3, 1);
});

it('initializes with default configuration', function () {
    $recovery = new FiberRecoveryManager($this->errorHandler);

    expect($recovery)->toBeInstanceOf(FiberRecoveryManager::class);
});

it('allows recovery on first attempt', function () {
    $fiber = new Fiber(fn () => null);
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getName')->andReturn('test-job');
    $job->shouldReceive('uuid')->andReturn('test-uuid');
    $job->shouldReceive('getJobId')->andReturn('test-uuid');

    $exception = new Exception('Test exception');

    $shouldRetry = $this->recovery->attemptRecovery($fiber, $job, $exception);

    expect($shouldRetry)->toBeTrue();
});

it('stops recovery after max retries', function () {
    $fiber = new Fiber(fn () => null);
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getName')->andReturn('test-job');
    $job->shouldReceive('uuid')->andReturn('test-uuid');
    $job->shouldReceive('getJobId')->andReturn('test-uuid');

    $exception = new Exception('Test exception');

    // Attempt recovery 4 times (max is 3)
    $this->recovery->attemptRecovery($fiber, $job, $exception);
    $this->recovery->attemptRecovery($fiber, $job, $exception);
    $this->recovery->attemptRecovery($fiber, $job, $exception);
    $shouldRetry = $this->recovery->attemptRecovery($fiber, $job, $exception);

    expect($shouldRetry)->toBeFalse();
});

it('tracks failed jobs', function () {
    $fiber = new Fiber(fn () => null);
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getName')->andReturn('test-job');
    $job->shouldReceive('uuid')->andReturn('test-uuid');
    $job->shouldReceive('getJobId')->andReturn('test-uuid');

    $exception = new Exception('Test exception');

    // Exhaust retries
    for ($i = 0; $i < 4; $i++) {
        $this->recovery->attemptRecovery($fiber, $job, $exception);
    }

    $failedJobs = $this->recovery->getFailedJobs();

    expect($failedJobs)->toBeEmpty(); // Failed jobs are removed after max retries
});

it('can reset failed jobs', function () {
    $fiber = new Fiber(fn () => null);
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getName')->andReturn('test-job');
    $job->shouldReceive('uuid')->andReturn('test-uuid');
    $job->shouldReceive('getJobId')->andReturn('test-uuid');

    $exception = new Exception('Test exception');

    // Attempt recovery once
    $this->recovery->attemptRecovery($fiber, $job, $exception);

    $this->recovery->reset();
    $failedJobs = $this->recovery->getFailedJobs();

    expect($failedJobs)->toBeEmpty();
});

it('increments metrics on recovery attempt', function () {
    $fiber = new Fiber(fn () => null);
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getName')->andReturn('test-job');
    $job->shouldReceive('uuid')->andReturn('test-uuid');
    $job->shouldReceive('getJobId')->andReturn('test-uuid');

    $exception = new Exception('Test exception');

    $this->recovery->attemptRecovery($fiber, $job, $exception);

    $snapshot = $this->metrics->getSnapshot();

    expect($snapshot)->toHaveKey('metrics');
    expect($snapshot['metrics']['jobs']['retried'])->toBeGreaterThan(0);
});

it('can get attempts for a job', function () {
    $fiber = new Fiber(fn () => null);
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getName')->andReturn('test-job');
    $job->shouldReceive('uuid')->andReturn('test-uuid');
    $job->shouldReceive('getJobId')->andReturn('job-123');

    $exception = new Exception('Test exception');

    expect($this->recovery->getAttempts('job-123'))->toBe(0);

    $this->recovery->attemptRecovery($fiber, $job, $exception);
    expect($this->recovery->getAttempts('job-123'))->toBe(1);

    $this->recovery->attemptRecovery($fiber, $job, $exception);
    expect($this->recovery->getAttempts('job-123'))->toBe(2);
});

it('can clear failures for a job', function () {
    $fiber = new Fiber(fn () => null);
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getName')->andReturn('test-job');
    $job->shouldReceive('uuid')->andReturn('test-uuid');
    $job->shouldReceive('getJobId')->andReturn('job-123');

    $exception = new Exception('Test exception');

    $this->recovery->attemptRecovery($fiber, $job, $exception);
    expect($this->recovery->getAttempts('job-123'))->toBe(1);

    $this->recovery->clearFailures('job-123');
    expect($this->recovery->getAttempts('job-123'))->toBe(0);
});

it('uses job id property if available', function () {
    $fiber = new Fiber(fn () => null);
    $job = new class
    {
        public $id = 'custom-id-123';
    };

    $exception = new Exception('Test exception');

    $this->recovery->attemptRecovery($fiber, $job, $exception);
    expect($this->recovery->getAttempts('custom-id-123'))->toBe(1);
});

it('uses job uuid property if id not available', function () {
    $fiber = new Fiber(fn () => null);
    $job = new class
    {
        public $uuid = 'custom-uuid-456';
    };

    $exception = new Exception('Test exception');

    $this->recovery->attemptRecovery($fiber, $job, $exception);
    expect($this->recovery->getAttempts('custom-uuid-456'))->toBe(1);
});

it('uses spl_object_hash as fallback identifier', function () {
    $fiber = new Fiber(fn () => null);
    $job = new stdClass;

    $exception = new Exception('Test exception');

    $jobId = spl_object_hash($job);

    $this->recovery->attemptRecovery($fiber, $job, $exception);
    expect($this->recovery->getAttempts($jobId))->toBe(1);
});

it('records job failed metric when max retries exceeded', function () {
    $fiber = new Fiber(fn () => null);
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getName')->andReturn('test-job');
    $job->shouldReceive('uuid')->andReturn('test-uuid');
    $job->shouldReceive('getJobId')->andReturn('job-123');

    $exception = new Exception('Test exception');

    // Exhaust retries
    for ($i = 0; $i < 4; $i++) {
        $this->recovery->attemptRecovery($fiber, $job, $exception);
    }

    $snapshot = $this->metrics->getSnapshot();
    expect($snapshot['metrics']['jobs']['failed'])->toBeGreaterThan(0);
});

it('can initialize with custom max retries and delay', function () {
    $recovery = new FiberRecoveryManager($this->errorHandler, $this->metrics, 5, 2);

    expect($recovery)->toBeInstanceOf(FiberRecoveryManager::class);
});

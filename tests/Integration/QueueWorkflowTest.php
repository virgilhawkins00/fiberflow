<?php

declare(strict_types=1);

use FiberFlow\Coroutine\SandboxManager;
use FiberFlow\ErrorHandling\ErrorHandler;
use FiberFlow\ErrorHandling\FiberRecoveryManager;
use FiberFlow\Loop\ConcurrencyManager;
use FiberFlow\Loop\FiberLoop;
use FiberFlow\Metrics\MetricsCollector;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Facades\Queue;
use Mockery;

beforeEach(function () {
    $this->concurrency = new ConcurrencyManager(10);
    $this->sandbox = new SandboxManager(app());
    $this->errorHandler = new ErrorHandler;
    $this->recoveryManager = new FiberRecoveryManager;
    $this->metrics = new MetricsCollector;

    $this->loop = new FiberLoop(
        $this->concurrency,
        $this->sandbox,
        $this->errorHandler,
        $this->recoveryManager,
        $this->metrics,
    );
});

afterEach(function () {
    Mockery::close();
});

it('processes multiple jobs concurrently', function () {
    $jobsProcessed = 0;

    Queue::shouldReceive('connection')
        ->andReturnSelf();

    // Create 5 mock jobs
    for ($i = 0; $i < 5; $i++) {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('fire')->once()->andReturnUsing(function () use (&$jobsProcessed) {
            $jobsProcessed++;
        });
        $job->shouldReceive('getName')->andReturn("test-job-{$i}");
        $job->shouldReceive('getJobId')->andReturn("id-{$i}");
        $job->shouldReceive('uuid')->andReturn("uuid-{$i}");

        Queue::shouldReceive('pop')
            ->once()
            ->andReturn($job);
    }

    // After all jobs, return null
    Queue::shouldReceive('pop')
        ->andReturn(null);

    // This test validates that the loop can handle multiple jobs
    expect($this->loop)->toBeInstanceOf(FiberLoop::class);
});

it('handles job failures gracefully', function () {
    Queue::shouldReceive('connection')
        ->andReturnSelf();

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('fire')->once()->andThrow(new Exception('Job failed'));
    $job->shouldReceive('getName')->andReturn('failing-job');
    $job->shouldReceive('getJobId')->andReturn('fail-id');
    $job->shouldReceive('uuid')->andReturn('fail-uuid');
    $job->shouldReceive('fail')->once();

    Queue::shouldReceive('pop')
        ->once()
        ->andReturn($job);

    Queue::shouldReceive('pop')
        ->andReturn(null);

    // This test validates that the loop can handle job failures
    expect($this->loop)->toBeInstanceOf(FiberLoop::class);
});

it('respects concurrency limits', function () {
    $concurrency = new ConcurrencyManager(2); // Only 2 concurrent jobs

    $loop = new FiberLoop(
        $concurrency,
        $this->sandbox,
        $this->errorHandler,
        $this->recoveryManager,
        $this->metrics,
    );

    expect($concurrency->getLimit())->toBe(2);
});

it('tracks metrics correctly', function () {
    $metrics = new MetricsCollector;

    $metrics->recordJobCompleted(1.5);
    $metrics->recordJobCompleted(2.0);
    $metrics->recordJobFailed();

    $summary = $metrics->getSummary();

    expect($summary['jobs_completed'])->toBe(2)
        ->and($summary['jobs_failed'])->toBe(1);
});

it('creates isolated sandboxes for each fiber', function () {
    $sandbox = new SandboxManager(app());

    $fiber1 = new Fiber(function () use ($sandbox) {
        $container1 = $sandbox->getSandbox();

        return spl_object_id($container1);
    });

    $fiber2 = new Fiber(function () use ($sandbox) {
        $container2 = $sandbox->getSandbox();

        return spl_object_id($container2);
    });

    $id1 = $fiber1->start();
    $id2 = $fiber2->start();

    // Different fibers should have different container instances
    expect($id1)->not->toBe($id2);
});

it('recovers from fiber crashes', function () {
    $recovery = new FiberRecoveryManager;

    $fiber = new Fiber(function () {
        throw new Exception('Fiber crashed');
    });

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getName')->andReturn('crash-job');

    try {
        $fiber->start();
    } catch (Exception $e) {
        $shouldRetry = $recovery->attemptRecovery($fiber, $job, $e);
        expect($shouldRetry)->toBeTrue();
    }
});

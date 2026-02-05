<?php

declare(strict_types=1);

use FiberFlow\Queue\JobBatch;
use Illuminate\Contracts\Queue\Job;

beforeEach(function () {
    $this->batch = new JobBatch('test-batch-id', 'Test Batch');
});

test('it has id and name', function () {
    expect($this->batch->id())->toBe('test-batch-id');
    expect($this->batch->name())->toBe('Test Batch');
});

test('it starts with no jobs', function () {
    expect($this->batch->totalCount())->toBe(0);
    expect($this->batch->completedCount())->toBe(0);
    expect($this->batch->failedCount())->toBe(0);
});

test('it adds jobs', function () {
    $job1 = Mockery::mock(Job::class);
    $job2 = Mockery::mock(Job::class);

    $this->batch->add($job1)->add($job2);

    expect($this->batch->totalCount())->toBe(2);
    expect($this->batch->jobs())->toHaveCount(2);
});

test('it tracks completed jobs', function () {
    $job = Mockery::mock(Job::class);
    $this->batch->add($job);

    $this->batch->markCompleted('job-1');

    expect($this->batch->completedCount())->toBe(1);
});

test('it tracks failed jobs', function () {
    $job = Mockery::mock(Job::class);
    $this->batch->add($job);

    $this->batch->markFailed('job-1');

    expect($this->batch->failedCount())->toBe(1);
});

test('it calculates progress', function () {
    $job1 = Mockery::mock(Job::class);
    $job2 = Mockery::mock(Job::class);
    $job3 = Mockery::mock(Job::class);

    $this->batch->add($job1)->add($job2)->add($job3);

    expect($this->batch->progress())->toBe(0.0);

    $this->batch->markCompleted('job-1');
    expect($this->batch->progress())->toBeGreaterThan(33.0);
    expect($this->batch->progress())->toBeLessThan(34.0);

    $this->batch->markCompleted('job-2');
    expect($this->batch->progress())->toBeGreaterThan(66.0);
    expect($this->batch->progress())->toBeLessThan(67.0);

    $this->batch->markCompleted('job-3');
    expect($this->batch->progress())->toBe(100.0);
});

test('it detects when finished', function () {
    $job1 = Mockery::mock(Job::class);
    $job2 = Mockery::mock(Job::class);

    $this->batch->add($job1)->add($job2);

    expect($this->batch->isFinished())->toBeFalse();

    $this->batch->markCompleted('job-1');
    expect($this->batch->isFinished())->toBeFalse();

    $this->batch->markCompleted('job-2');
    expect($this->batch->isFinished())->toBeTrue();
});

test('it detects failures', function () {
    $job = Mockery::mock(Job::class);
    $this->batch->add($job);

    expect($this->batch->hasFailures())->toBeFalse();

    $this->batch->markFailed('job-1');

    expect($this->batch->hasFailures())->toBeTrue();
});

test('it runs then callback on success', function () {
    $job = Mockery::mock(Job::class);
    $this->batch->add($job);

    $called = false;
    $this->batch->then(function ($batch) use (&$called) {
        $called = true;
    });

    $this->batch->markCompleted('job-1');

    expect($called)->toBeTrue();
});

test('it runs catch callback on failure', function () {
    $job = Mockery::mock(Job::class);
    $this->batch->add($job);

    $called = false;
    $this->batch->catch(function ($batch) use (&$called) {
        $called = true;
    });

    $this->batch->markFailed('job-1');

    expect($called)->toBeTrue();
});

test('it runs finally callback always', function () {
    $job = Mockery::mock(Job::class);
    $this->batch->add($job);

    $calledOnSuccess = false;
    $calledOnFailure = false;

    $this->batch->finally(function ($batch) use (&$calledOnSuccess) {
        $calledOnSuccess = true;
    });

    $this->batch->markCompleted('job-1');
    expect($calledOnSuccess)->toBeTrue();

    // Test with failure
    $batch2 = new JobBatch('batch-2');
    $batch2->add(Mockery::mock(Job::class));
    $batch2->finally(function ($batch) use (&$calledOnFailure) {
        $calledOnFailure = true;
    });

    $batch2->markFailed('job-1');
    expect($calledOnFailure)->toBeTrue();
});

afterEach(function () {
    Mockery::close();
});

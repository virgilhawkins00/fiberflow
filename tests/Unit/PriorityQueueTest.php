<?php

declare(strict_types=1);

use FiberFlow\Queue\PriorityQueue;
use Illuminate\Contracts\Queue\Job;

beforeEach(function () {
    $this->queue = new PriorityQueue;
});

test('it starts empty', function () {
    expect($this->queue->isEmpty())->toBeTrue();
    expect($this->queue->count())->toBe(0);
});

test('it pushes and pops jobs', function () {
    $job = Mockery::mock(Job::class);

    $this->queue->push($job);

    expect($this->queue->isEmpty())->toBeFalse();
    expect($this->queue->count())->toBe(1);

    $popped = $this->queue->pop();
    expect($popped)->toBe($job);
    expect($this->queue->isEmpty())->toBeTrue();
});

test('it processes higher priority jobs first', function () {
    $lowPriorityJob = Mockery::mock(Job::class);
    $highPriorityJob = Mockery::mock(Job::class);

    $this->queue->push($lowPriorityJob, 1);
    $this->queue->push($highPriorityJob, 10);

    expect($this->queue->pop())->toBe($highPriorityJob);
    expect($this->queue->pop())->toBe($lowPriorityJob);
});

test('it processes same priority jobs in FIFO order', function () {
    $job1 = Mockery::mock(Job::class);
    $job2 = Mockery::mock(Job::class);
    $job3 = Mockery::mock(Job::class);

    $this->queue->push($job1, 5);
    $this->queue->push($job2, 5);
    $this->queue->push($job3, 5);

    expect($this->queue->pop())->toBe($job1);
    expect($this->queue->pop())->toBe($job2);
    expect($this->queue->pop())->toBe($job3);
});

test('it handles mixed priorities correctly', function () {
    $jobs = [];
    for ($i = 0; $i < 5; $i++) {
        $jobs[$i] = Mockery::mock(Job::class);
    }

    $this->queue->push($jobs[0], 1);  // Low
    $this->queue->push($jobs[1], 10); // High
    $this->queue->push($jobs[2], 5);  // Medium
    $this->queue->push($jobs[3], 10); // High (FIFO with jobs[1])
    $this->queue->push($jobs[4], 1);  // Low (FIFO with jobs[0])

    expect($this->queue->pop())->toBe($jobs[1]); // First high priority
    expect($this->queue->pop())->toBe($jobs[3]); // Second high priority
    expect($this->queue->pop())->toBe($jobs[2]); // Medium priority
    expect($this->queue->pop())->toBe($jobs[0]); // First low priority
    expect($this->queue->pop())->toBe($jobs[4]); // Second low priority
});

test('it returns null when popping from empty queue', function () {
    expect($this->queue->pop())->toBeNull();
});

test('it clears all jobs', function () {
    $job1 = Mockery::mock(Job::class);
    $job2 = Mockery::mock(Job::class);

    $this->queue->push($job1);
    $this->queue->push($job2);

    expect($this->queue->count())->toBe(2);

    $this->queue->clear();

    expect($this->queue->isEmpty())->toBeTrue();
    expect($this->queue->count())->toBe(0);
});

afterEach(function () {
    Mockery::close();
});

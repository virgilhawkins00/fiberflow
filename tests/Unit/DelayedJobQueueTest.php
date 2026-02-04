<?php

declare(strict_types=1);

use FiberFlow\Queue\DelayedJobQueue;
use Illuminate\Contracts\Queue\Job;

beforeEach(function () {
    $this->queue = new DelayedJobQueue();
});

test('it starts empty', function () {
    expect($this->queue->isEmpty())->toBeTrue();
    expect($this->queue->count())->toBe(0);
});

test('it pushes delayed jobs', function () {
    $job = Mockery::mock(Job::class);
    
    $this->queue->push($job, 5); // 5 second delay
    
    expect($this->queue->isEmpty())->toBeFalse();
    expect($this->queue->count())->toBe(1);
});

test('it does not return jobs before delay expires', function () {
    $job = Mockery::mock(Job::class);
    
    $this->queue->push($job, 10); // 10 second delay
    
    $ready = $this->queue->getReadyJobs();
    expect($ready)->toBeEmpty();
});

test('it returns jobs after delay expires', function () {
    $job = Mockery::mock(Job::class);
    
    $this->queue->push($job, 0); // No delay
    
    $ready = $this->queue->getReadyJobs();
    expect($ready)->toHaveCount(1);
    expect($ready[0])->toBe($job);
});

test('it removes jobs after returning them', function () {
    $job = Mockery::mock(Job::class);
    
    $this->queue->push($job, 0);
    
    $this->queue->getReadyJobs();
    
    expect($this->queue->isEmpty())->toBeTrue();
});

test('it sorts jobs by availability time', function () {
    $job1 = Mockery::mock(Job::class);
    $job2 = Mockery::mock(Job::class);
    $job3 = Mockery::mock(Job::class);
    
    $this->queue->push($job1, 3);
    $this->queue->push($job2, 1);
    $this->queue->push($job3, 2);
    
    $all = $this->queue->all();
    
    // Should be sorted by availableAt
    expect($all[0]['job'])->toBe($job2); // 1 second delay
    expect($all[1]['job'])->toBe($job3); // 2 second delay
    expect($all[2]['job'])->toBe($job1); // 3 second delay
});

test('it calculates time until next job', function () {
    $job = Mockery::mock(Job::class);
    
    $this->queue->push($job, 5);
    
    $timeUntilNext = $this->queue->getTimeUntilNext();
    
    expect($timeUntilNext)->toBeGreaterThan(4.9);
    expect($timeUntilNext)->toBeLessThanOrEqual(5.0);
});

test('it returns null time when queue is empty', function () {
    expect($this->queue->getTimeUntilNext())->toBeNull();
});

test('it gets next ready job', function () {
    $job1 = Mockery::mock(Job::class);
    $job2 = Mockery::mock(Job::class);
    
    $this->queue->push($job1, 0);
    $this->queue->push($job2, 10);
    
    $next = $this->queue->getNextJob();
    expect($next)->toBe($job1);
});

test('it clears all jobs', function () {
    $job1 = Mockery::mock(Job::class);
    $job2 = Mockery::mock(Job::class);
    
    $this->queue->push($job1, 5);
    $this->queue->push($job2, 10);
    
    expect($this->queue->count())->toBe(2);
    
    $this->queue->clear();
    
    expect($this->queue->isEmpty())->toBeTrue();
});

afterEach(function () {
    Mockery::close();
});


<?php

declare(strict_types=1);

use FiberFlow\Metrics\MetricsCollector;

test('it initializes with default metrics', function () {
    $metrics = new MetricsCollector();
    $all = $metrics->getAllMetrics();

    expect($all)->toHaveKey('jobs');
    expect($all)->toHaveKey('fibers');
    expect($all)->toHaveKey('memory');
    expect($all)->toHaveKey('performance');
    expect($all['jobs']['processed'])->toBe(0);
    expect($all['fibers']['active'])->toBe(0);
});

test('it can increment counters', function () {
    $metrics = new MetricsCollector();

    $metrics->increment('jobs', 'processed');
    expect($metrics->get('jobs', 'processed'))->toBe(1);

    $metrics->increment('jobs', 'processed', 5);
    expect($metrics->get('jobs', 'processed'))->toBe(6);
});

test('it can set metric values', function () {
    $metrics = new MetricsCollector();

    $metrics->set('custom', 'value', 42);
    expect($metrics->get('custom', 'value'))->toBe(42);
});

test('it records job completions correctly', function () {
    $metrics = new MetricsCollector();

    $metrics->recordJobCompleted(0.5);
    expect($metrics->get('jobs', 'processed'))->toBe(1);
    expect($metrics->get('jobs', 'total'))->toBe(1);
    expect($metrics->get('performance', 'avg_job_time'))->toBe(0.5);

    $metrics->recordJobCompleted(1.5);
    expect($metrics->get('jobs', 'processed'))->toBe(2);
    expect($metrics->get('jobs', 'total'))->toBe(2);
    expect($metrics->get('performance', 'avg_job_time'))->toBe(1.0);
});

test('it records job failures correctly', function () {
    $metrics = new MetricsCollector();

    $metrics->recordJobFailed();
    expect($metrics->get('jobs', 'failed'))->toBe(1);
    expect($metrics->get('jobs', 'total'))->toBe(1);
});

test('it records job retries correctly', function () {
    $metrics = new MetricsCollector();

    $metrics->recordJobRetried();
    expect($metrics->get('jobs', 'retried'))->toBe(1);
});

test('it records fiber spawns correctly', function () {
    $metrics = new MetricsCollector();

    $metrics->recordFiberSpawned();
    expect($metrics->get('fibers', 'spawned'))->toBe(1);
    expect($metrics->get('fibers', 'active'))->toBe(1);

    $metrics->recordFiberSpawned();
    expect($metrics->get('fibers', 'spawned'))->toBe(2);
    expect($metrics->get('fibers', 'active'))->toBe(2);
});

test('it records fiber completions correctly', function () {
    $metrics = new MetricsCollector();

    $metrics->recordFiberSpawned();
    $metrics->recordFiberCompleted();

    expect($metrics->get('fibers', 'completed'))->toBe(1);
    expect($metrics->get('fibers', 'active'))->toBe(0);
});

test('it records fiber failures correctly', function () {
    $metrics = new MetricsCollector();

    $metrics->recordFiberSpawned();
    $metrics->recordFiberFailed();

    expect($metrics->get('fibers', 'failed'))->toBe(1);
    expect($metrics->get('fibers', 'active'))->toBe(0);
});

test('it updates memory metrics', function () {
    $metrics = new MetricsCollector();

    $metrics->updateMemoryMetrics();

    expect($metrics->get('memory', 'current'))->toBeGreaterThan(0);
    expect($metrics->get('memory', 'peak'))->toBeGreaterThan(0);
});

test('it updates performance metrics', function () {
    $metrics = new MetricsCollector();

    // Record some jobs
    $metrics->recordJobCompleted(0.1);
    $metrics->recordJobCompleted(0.2);

    $metrics->updatePerformanceMetrics();

    expect($metrics->get('performance', 'uptime'))->toBeGreaterThan(0);
    expect($metrics->get('performance', 'throughput'))->toBeGreaterThan(0);
});

test('it can get a snapshot of metrics', function () {
    $metrics = new MetricsCollector();

    $metrics->recordJobCompleted(0.5);
    $metrics->recordFiberSpawned();

    $snapshot = $metrics->getSnapshot();

    expect($snapshot)->toHaveKey('timestamp');
    expect($snapshot)->toHaveKey('metrics');
    expect($snapshot['metrics']['jobs']['processed'])->toBe(1);
    expect($snapshot['metrics']['fibers']['spawned'])->toBe(1);
});

test('it can reset all metrics', function () {
    $metrics = new MetricsCollector();

    $metrics->recordJobCompleted(0.5);
    $metrics->recordFiberSpawned();

    expect($metrics->get('jobs', 'processed'))->toBe(1);

    $metrics->reset();

    expect($metrics->get('jobs', 'processed'))->toBe(0);
    expect($metrics->get('fibers', 'spawned'))->toBe(0);
});

test('it calculates throughput correctly', function () {
    $metrics = new MetricsCollector();

    // Simulate processing 10 jobs
    for ($i = 0; $i < 10; $i++) {
        $metrics->recordJobCompleted(0.1);
    }

    // Wait a bit to ensure uptime > 0
    usleep(10000);

    $metrics->updatePerformanceMetrics();

    $throughput = $metrics->get('performance', 'throughput');
    expect($throughput)->toBeGreaterThan(0);
});


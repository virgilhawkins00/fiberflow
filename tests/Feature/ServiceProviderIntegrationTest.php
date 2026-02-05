<?php

declare(strict_types=1);

use FiberFlow\Coroutine\SandboxManager;
use FiberFlow\Http\AsyncHttpClient;
use FiberFlow\Loop\ConcurrencyManager;
use FiberFlow\Loop\FiberLoop;
use FiberFlow\Metrics\MetricsCollector;

test('it can resolve concurrency manager from container', function () {
    config(['fiberflow.max_concurrency' => 50]);

    $manager = app(ConcurrencyManager::class);

    expect($manager)->toBeInstanceOf(ConcurrencyManager::class);
});

test('it can resolve concurrency manager with custom config', function () {
    config(['fiberflow.max_concurrency' => 100]);

    $manager = app(ConcurrencyManager::class);

    expect($manager)->toBeInstanceOf(ConcurrencyManager::class);
});

test('it can resolve fiber loop from container', function () {
    $loop = app(FiberLoop::class);

    expect($loop)->toBeInstanceOf(FiberLoop::class);
});

test('it can resolve metrics collector from container', function () {
    $metrics = app(MetricsCollector::class);

    expect($metrics)->toBeInstanceOf(MetricsCollector::class);
});

test('it can resolve async http client from container', function () {
    config(['fiberflow.http.timeout' => 30]);
    config(['fiberflow.http.retry_attempts' => 3]);
    config(['fiberflow.http.retry_delay' => 1000]);

    $client = app('fiberflow.http');

    expect($client)->toBeInstanceOf(AsyncHttpClient::class);
});

test('it can resolve async http client with custom config', function () {
    config(['fiberflow.http.timeout' => 60]);
    config(['fiberflow.http.retry_attempts' => 5]);
    config(['fiberflow.http.retry_delay' => 2000]);

    $client = app('fiberflow.http');

    expect($client)->toBeInstanceOf(AsyncHttpClient::class);
});

test('it can resolve sandbox manager from container', function () {
    $sandbox = app(SandboxManager::class);

    expect($sandbox)->toBeInstanceOf(SandboxManager::class);
});

test('services are registered as singletons', function () {
    $manager1 = app(ConcurrencyManager::class);
    $manager2 = app(ConcurrencyManager::class);

    expect($manager1)->toBe($manager2);
});

test('http client is registered as singleton', function () {
    $client1 = app('fiberflow.http');
    $client2 = app('fiberflow.http');

    expect($client1)->toBe($client2);
});

test('fiber loop is registered as singleton', function () {
    $loop1 = app(FiberLoop::class);
    $loop2 = app(FiberLoop::class);

    expect($loop1)->toBe($loop2);
});

<?php

declare(strict_types=1);

use FiberFlow\Coroutine\ConcurrencyManager;
use FiberFlow\Coroutine\SandboxManager;
use FiberFlow\FiberFlowServiceProvider;
use FiberFlow\Loop\FiberLoop;
use FiberFlow\Metrics\MetricsCollector;

beforeEach(function () {
    $this->provider = new FiberFlowServiceProvider($this->app);
});

it('has register method', function () {
    expect(method_exists($this->provider, 'register'))->toBeTrue();
});

it('has boot method', function () {
    expect(method_exists($this->provider, 'boot'))->toBeTrue();
});

it('has provides method', function () {
    expect(method_exists($this->provider, 'provides'))->toBeTrue();
});

it('has registerCoreServices method', function () {
    $reflection = new ReflectionClass($this->provider);
    expect($reflection->hasMethod('registerCoreServices'))->toBeTrue();
});

it('has registerFacades method', function () {
    $reflection = new ReflectionClass($this->provider);
    expect($reflection->hasMethod('registerFacades'))->toBeTrue();
});

it('resolves sandbox manager from container', function () {
    $this->provider->register();

    $manager = $this->app->make(SandboxManager::class);

    expect($manager)->toBeInstanceOf(SandboxManager::class);
});

it('resolves metrics collector from container', function () {
    $this->provider->register();

    $metrics = $this->app->make(MetricsCollector::class);

    expect($metrics)->toBeInstanceOf(MetricsCollector::class);
});

it('provides list of services', function () {
    $services = $this->provider->provides();

    expect($services)->toBeArray();
    expect($services)->toHaveCount(7);
    expect(in_array(SandboxManager::class, $services))->toBeTrue();
    expect(in_array('fiberflow.http', $services))->toBeTrue();
});

it('registers async database connection', function () {
    $this->provider->register();

    expect($this->app->bound(\FiberFlow\Database\AsyncDbConnection::class))->toBeTrue();
});

it('resolves fiber-aware auth facade', function () {
    $this->provider->register();

    $auth = $this->app->make('fiberflow.auth');

    expect($auth)->not->toBeNull();
});

it('resolves fiber-aware cache facade', function () {
    $this->provider->register();

    $cache = $this->app->make('fiberflow.cache');

    expect($cache)->not->toBeNull();
});

it('resolves fiber-aware session facade', function () {
    $this->provider->register();

    $session = $this->app->make('fiberflow.session');

    expect($session)->not->toBeNull();
});


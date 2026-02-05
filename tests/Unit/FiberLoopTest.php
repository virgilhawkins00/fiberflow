<?php

declare(strict_types=1);

use FiberFlow\Coroutine\SandboxManager;
use FiberFlow\ErrorHandling\ErrorHandler;
use FiberFlow\ErrorHandling\FiberRecoveryManager;
use FiberFlow\Loop\ConcurrencyManager;
use FiberFlow\Loop\FiberLoop;
use FiberFlow\Metrics\MetricsCollector;

beforeEach(function () {
    $this->concurrency = new ConcurrencyManager(10);
    $this->sandbox = new SandboxManager(app());
    $this->metrics = new MetricsCollector();
    $this->errorHandler = new ErrorHandler($this->metrics);
    $this->recovery = new FiberRecoveryManager($this->errorHandler, $this->metrics);
    
    $this->loop = new FiberLoop(
        $this->concurrency,
        $this->sandbox,
        $this->errorHandler,
        $this->recovery,
        $this->metrics
    );
});

it('initializes with dependencies', function () {
    expect($this->loop)->toBeInstanceOf(FiberLoop::class);
});

it('initializes with default error handler and recovery manager', function () {
    $loop = new FiberLoop($this->concurrency, $this->sandbox);
    
    expect($loop)->toBeInstanceOf(FiberLoop::class);
});




<?php

declare(strict_types=1);

use FiberFlow\Console\DashboardController;

beforeEach(function () {
    $this->controller = new DashboardController();
});

it('initializes in running state', function () {
    expect($this->controller->isRunning())->toBeTrue();
    expect($this->controller->isPaused())->toBeFalse();
    expect($this->controller->isStopping())->toBeFalse();
    expect($this->controller->isStopped())->toBeFalse();
});

it('can pause the worker', function () {
    $this->controller->pause();
    
    expect($this->controller->isPaused())->toBeTrue();
    expect($this->controller->isRunning())->toBeFalse();
});

it('can resume the worker', function () {
    $this->controller->pause();
    $this->controller->resume();
    
    expect($this->controller->isRunning())->toBeTrue();
    expect($this->controller->isPaused())->toBeFalse();
});

it('can stop the worker gracefully', function () {
    $this->controller->stop();
    
    expect($this->controller->isStopping())->toBeTrue();
    expect($this->controller->isRunning())->toBeFalse();
});

it('can force stop the worker', function () {
    $this->controller->forceStop();
    
    expect($this->controller->isStopped())->toBeTrue();
    expect($this->controller->isRunning())->toBeFalse();
});

it('can get current state', function () {
    expect($this->controller->getState())->toBe('running');
    
    $this->controller->pause();
    expect($this->controller->getState())->toBe('paused');
    
    $this->controller->stop();
    expect($this->controller->getState())->toBe('stopping');
    
    $this->controller->forceStop();
    expect($this->controller->getState())->toBe('stopped');
});

it('cannot pause when already paused', function () {
    $this->controller->pause();
    $this->controller->pause(); // Should have no effect
    
    expect($this->controller->isPaused())->toBeTrue();
});

it('cannot resume when not paused', function () {
    $this->controller->resume(); // Should have no effect
    
    expect($this->controller->isRunning())->toBeTrue();
});

it('can toggle between running and paused', function () {
    expect($this->controller->isRunning())->toBeTrue();
    
    $this->controller->pause();
    expect($this->controller->isPaused())->toBeTrue();
    
    $this->controller->resume();
    expect($this->controller->isRunning())->toBeTrue();
});


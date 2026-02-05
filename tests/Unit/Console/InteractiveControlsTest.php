<?php

declare(strict_types=1);

use FiberFlow\Console\InteractiveControls;

beforeEach(function () {
    $this->controls = new InteractiveControls;
});

it('initializes with default state', function () {
    expect($this->controls)->toBeInstanceOf(InteractiveControls::class);
});

it('can pause the worker', function () {
    $this->controls->pause();

    // Use reflection to check state
    $reflection = new ReflectionClass($this->controls);
    $stateProperty = $reflection->getProperty('state');
    $stateProperty->setAccessible(true);

    expect($stateProperty->getValue($this->controls))->toBe('paused');
});

it('can resume the worker', function () {
    $this->controls->pause();
    $this->controls->resume();

    // Use reflection to check state
    $reflection = new ReflectionClass($this->controls);
    $stateProperty = $reflection->getProperty('state');
    $stateProperty->setAccessible(true);

    expect($stateProperty->getValue($this->controls))->toBe('running');
});

it('can stop the worker', function () {
    $this->controls->stop();

    // Use reflection to check state
    $reflection = new ReflectionClass($this->controls);
    $stateProperty = $reflection->getProperty('state');
    $stateProperty->setAccessible(true);
    $shouldStopProperty = $reflection->getProperty('shouldStop');
    $shouldStopProperty->setAccessible(true);

    expect($stateProperty->getValue($this->controls))->toBe('stopping')
        ->and($shouldStopProperty->getValue($this->controls))->toBeTrue();
});

it('can check if should stop', function () {
    expect($this->controls->shouldStop())->toBeFalse();

    $this->controls->stop();

    expect($this->controls->shouldStop())->toBeTrue();
});

it('can get current state', function () {
    expect($this->controls->getState())->toBe('running');

    $this->controls->pause();
    expect($this->controls->getState())->toBe('paused');

    $this->controls->resume();
    expect($this->controls->getState())->toBe('running');

    $this->controls->stop();
    expect($this->controls->getState())->toBe('stopping');
});

it('can check if paused', function () {
    expect($this->controls->isPaused())->toBeFalse();

    $this->controls->pause();
    expect($this->controls->isPaused())->toBeTrue();

    $this->controls->resume();
    expect($this->controls->isPaused())->toBeFalse();
});

it('can check if running', function () {
    expect($this->controls->isRunning())->toBeTrue();

    $this->controls->pause();
    expect($this->controls->isRunning())->toBeFalse();

    $this->controls->resume();
    expect($this->controls->isRunning())->toBeTrue();
});

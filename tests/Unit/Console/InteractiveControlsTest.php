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

it('can get available commands', function () {
    $commands = $this->controls->getCommands();

    expect($commands)->toBeArray();
    expect($commands)->toHaveKey('p');
    expect($commands)->toHaveKey('r');
    expect($commands)->toHaveKey('s');
    expect($commands)->toHaveKey('q');
    expect($commands)->toHaveKey('h');
});

it('can process pause input', function () {
    $result = $this->controls->processInput('p');

    expect($result)->toBeString();
    expect($this->controls->isPaused())->toBeTrue();
});

it('can process resume input', function () {
    $this->controls->pause();
    $result = $this->controls->processInput('r');

    expect($result)->toBeString();
    expect($this->controls->isRunning())->toBeTrue();
});

it('can process stop input', function () {
    $result = $this->controls->processInput('s');

    expect($result)->toBeString();
    expect($this->controls->shouldStop())->toBeTrue();
});

it('can process quit input', function () {
    $result = $this->controls->processInput('q');

    expect($result)->toBeString();
    expect($this->controls->shouldStop())->toBeTrue();
});

it('can process help input', function () {
    $result = $this->controls->processInput('h');

    expect($result)->toBeString();
    expect($result)->toContain('Available commands');
});

it('returns null for unknown input', function () {
    $result = $this->controls->processInput('x');

    expect($result)->toBeNull();
});

it('handles pause when already paused', function () {
    $this->controls->pause();
    $result = $this->controls->processInput('p');

    expect($result)->toContain('already paused');
});

it('handles resume when already running', function () {
    $result = $this->controls->processInput('r');

    expect($result)->toContain('already running');
});

it('has setupSignalHandlers method', function () {
    $reflection = new ReflectionClass($this->controls);
    expect($reflection->hasMethod('setupSignalHandlers'))->toBeTrue();
});

it('has handlePause method', function () {
    $reflection = new ReflectionClass($this->controls);
    expect($reflection->hasMethod('handlePause'))->toBeTrue();
});

it('has handleResume method', function () {
    $reflection = new ReflectionClass($this->controls);
    expect($reflection->hasMethod('handleResume'))->toBeTrue();
});

it('has handleStop method', function () {
    $reflection = new ReflectionClass($this->controls);
    expect($reflection->hasMethod('handleStop'))->toBeTrue();
});

it('has handleHelp method', function () {
    $reflection = new ReflectionClass($this->controls);
    expect($reflection->hasMethod('handleHelp'))->toBeTrue();
});

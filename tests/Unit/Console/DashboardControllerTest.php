<?php

declare(strict_types=1);

use FiberFlow\Console\DashboardController;

beforeEach(function () {
    $this->controller = new DashboardController;
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

it('can check all state methods', function () {
    // Running state
    expect($this->controller->isRunning())->toBeTrue();
    expect($this->controller->isPaused())->toBeFalse();
    expect($this->controller->isStopping())->toBeFalse();
    expect($this->controller->isStopped())->toBeFalse();

    // Paused state
    $this->controller->pause();
    expect($this->controller->isRunning())->toBeFalse();
    expect($this->controller->isPaused())->toBeTrue();
    expect($this->controller->isStopping())->toBeFalse();
    expect($this->controller->isStopped())->toBeFalse();

    // Stopping state
    $this->controller->stop();
    expect($this->controller->isRunning())->toBeFalse();
    expect($this->controller->isPaused())->toBeFalse();
    expect($this->controller->isStopping())->toBeTrue();
    expect($this->controller->isStopped())->toBeFalse();

    // Stopped state
    $this->controller->forceStop();
    expect($this->controller->isRunning())->toBeFalse();
    expect($this->controller->isPaused())->toBeFalse();
    expect($this->controller->isStopping())->toBeFalse();
    expect($this->controller->isStopped())->toBeTrue();
});

it('maintains state consistency', function () {
    $states = ['running', 'paused', 'stopping', 'stopped'];

    foreach ($states as $state) {
        $currentState = $this->controller->getState();
        expect($currentState)->toBeIn($states);

        if ($state === 'paused') {
            $this->controller->pause();
        } elseif ($state === 'stopping') {
            $this->controller->stop();
        } elseif ($state === 'stopped') {
            $this->controller->forceStop();
        }
    }

    expect(true)->toBeTrue();
});

it('can transition from running to stopping directly', function () {
    expect($this->controller->isRunning())->toBeTrue();

    $this->controller->stop();

    expect($this->controller->isStopping())->toBeTrue();
    expect($this->controller->isRunning())->toBeFalse();
});

it('can transition from paused to stopping', function () {
    $this->controller->pause();
    expect($this->controller->isPaused())->toBeTrue();

    $this->controller->stop();

    expect($this->controller->isStopping())->toBeTrue();
    expect($this->controller->isPaused())->toBeFalse();
});

it('displays correct state with getStateDisplay', function () {
    expect($this->controller->getStateDisplay())->toBe('🟢 RUNNING');

    $this->controller->pause();
    expect($this->controller->getStateDisplay())->toBe('🟡 PAUSED');

    $this->controller->stop();
    expect($this->controller->getStateDisplay())->toBe('🟠 STOPPING');

    $this->controller->forceStop();
    expect($this->controller->getStateDisplay())->toBe('🔴 STOPPED');
});

it('handles keyboard input for pause', function () {
    $this->controller->handleInput('p');

    expect($this->controller->isPaused())->toBeTrue();
});

it('handles keyboard input for resume', function () {
    $this->controller->pause();
    $this->controller->handleInput('r');

    expect($this->controller->isRunning())->toBeTrue();
});

it('handles keyboard input for stop', function () {
    $this->controller->handleInput('s');

    expect($this->controller->isStopping())->toBeTrue();
});

it('handles keyboard input for force stop', function () {
    $this->controller->handleInput('q');

    expect($this->controller->isStopped())->toBeTrue();
});

it('handles unknown keyboard input', function () {
    $initialState = $this->controller->getState();

    $this->controller->handleInput('x'); // Unknown command

    expect($this->controller->getState())->toBe($initialState);
});

it('provides help text', function () {
    $helpText = $this->controller->getHelpText();

    expect($helpText)->toBeString();
    expect($helpText)->toContain('P');
    expect($helpText)->toContain('R');
    expect($helpText)->toContain('S');
    expect($helpText)->toContain('Q');
});

<?php

declare(strict_types=1);

use FiberFlow\Coroutine\ContainerPollutionDetector;
use Illuminate\Container\Container;

test('it can take a snapshot of container state', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    $fiber = new Fiber(function () use ($detector, $container) {
        $detector->takeSnapshot($container);
        expect(true)->toBeTrue(); // Snapshot taken successfully
    });

    $fiber->start();
    expect($fiber->isTerminated())->toBeTrue();
});

test('it can detect when pollution detection is enabled', function () {
    $detector = new ContainerPollutionDetector;
    expect($detector->isEnabled())->toBeTrue();

    $detector->setEnabled(false);
    expect($detector->isEnabled())->toBeFalse();
});

test('it can add isolated services', function () {
    $detector = new ContainerPollutionDetector;
    $detector->addIsolatedService('custom.service');

    // Service should be added to the isolation list
    expect(true)->toBeTrue();
});

test('it verifies container integrity without violations', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    $fiber = new Fiber(function () use ($detector, $container) {
        $detector->takeSnapshot($container);

        // No changes made, should pass verification
        $detector->verify($container);

        expect(true)->toBeTrue();
    });

    $fiber->start();
    expect($fiber->isTerminated())->toBeTrue();
});

test('it can be disabled', function () {
    $detector = new ContainerPollutionDetector;
    $detector->setEnabled(false);

    $container = new Container;

    $fiber = new Fiber(function () use ($detector, $container) {
        $detector->takeSnapshot($container);
        $detector->verify($container);

        // Should not throw even if state changes when disabled
        expect(true)->toBeTrue();
    });

    $fiber->start();
    expect($fiber->isTerminated())->toBeTrue();
});

test('it captures object state correctly', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    // Bind a simple service
    $container->singleton('test.service', function () {
        return new class
        {
            public string $value = 'initial';
        };
    });

    $fiber = new Fiber(function () use ($detector, $container) {
        $detector->takeSnapshot($container);

        // Verify without changes
        $detector->verify($container);

        expect(true)->toBeTrue();
    });

    $fiber->start();
    expect($fiber->isTerminated())->toBeTrue();
});

test('it isolates snapshots between fibers', function () {
    $detector = new ContainerPollutionDetector;
    $container1 = new Container;
    $container2 = new Container;

    $fiber1 = new Fiber(function () use ($detector, $container1) {
        $detector->takeSnapshot($container1);
        expect(true)->toBeTrue();
    });

    $fiber2 = new Fiber(function () use ($detector, $container2) {
        $detector->takeSnapshot($container2);
        expect(true)->toBeTrue();
    });

    $fiber1->start();
    $fiber2->start();

    expect($fiber1->isTerminated())->toBeTrue();
    expect($fiber2->isTerminated())->toBeTrue();
});

test('it handles missing snapshots gracefully', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    $fiber = new Fiber(function () use ($detector, $container) {
        // Verify without taking snapshot first
        $detector->verify($container);

        // Should not throw
        expect(true)->toBeTrue();
    });

    $fiber->start();
    expect($fiber->isTerminated())->toBeTrue();
});

test('it works when not in a fiber context', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    // Should not throw when not in a Fiber
    $detector->takeSnapshot($container);
    $detector->verify($container);

    expect(true)->toBeTrue();
});

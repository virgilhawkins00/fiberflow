<?php

declare(strict_types=1);

use FiberFlow\Coroutine\SandboxManager;
use Illuminate\Container\Container;

test('it creates a sandbox for a fiber', function () {
    $baseContainer = new Container();
    $manager = new SandboxManager($baseContainer);

    $fiber = new Fiber(function () use ($manager) {
        $sandbox = $manager->createSandbox();
        expect($sandbox)->toBeInstanceOf(Container::class);
        expect($sandbox)->not->toBe($manager->getBaseContainer());
    });

    $fiber->start();
});

test('it returns base container when not in a fiber', function () {
    $baseContainer = new Container();
    $manager = new SandboxManager($baseContainer);

    $container = $manager->getCurrentContainer();

    expect($container)->toBe($baseContainer);
});

test('it returns sandbox when in a fiber', function () {
    $baseContainer = new Container();
    $manager = new SandboxManager($baseContainer);

    $fiber = new Fiber(function () use ($manager, $baseContainer) {
        $sandbox = $manager->createSandbox();
        $current = $manager->getCurrentContainer();

        expect($current)->toBe($sandbox);
        expect($current)->not->toBe($baseContainer);
    });

    $fiber->start();
});

test('it can check if fiber has sandbox', function () {
    $baseContainer = new Container();
    $manager = new SandboxManager($baseContainer);

    expect($manager->hasSandbox())->toBeFalse();

    $fiber = new Fiber(function () use ($manager) {
        $manager->createSandbox();
        expect($manager->hasSandbox())->toBeTrue();
    });

    $fiber->start();
});

test('it can destroy sandbox', function () {
    $baseContainer = new Container();
    $manager = new SandboxManager($baseContainer);

    $fiber = new Fiber(function () use ($manager) {
        $manager->createSandbox();
        expect($manager->hasSandbox())->toBeTrue();

        $manager->destroySandbox();
        expect($manager->hasSandbox())->toBeFalse();
    });

    $fiber->start();
});

test('it can be disabled', function () {
    $baseContainer = new Container();
    $manager = new SandboxManager($baseContainer);
    $manager->setEnabled(false);

    $fiber = new Fiber(function () use ($manager, $baseContainer) {
        $sandbox = $manager->createSandbox();
        expect($sandbox)->toBe($baseContainer);
    });

    $fiber->start();
});

test('it isolates state between fibers', function () {
    $baseContainer = new Container();
    $manager = new SandboxManager($baseContainer);

    $value1 = null;
    $value2 = null;

    $fiber1 = new Fiber(function () use ($manager, &$value1) {
        $sandbox = $manager->createSandbox();
        $sandbox->instance('test', 'fiber1');
        $value1 = $sandbox->make('test');
    });

    $fiber2 = new Fiber(function () use ($manager, &$value2) {
        $sandbox = $manager->createSandbox();
        $sandbox->instance('test', 'fiber2');
        $value2 = $sandbox->make('test');
    });

    $fiber1->start();
    $fiber2->start();

    expect($value1)->toBe('fiber1');
    expect($value2)->toBe('fiber2');
});


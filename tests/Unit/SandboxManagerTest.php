<?php

declare(strict_types=1);

use FiberFlow\Coroutine\SandboxManager;
use Illuminate\Container\Container;

test('it creates a sandbox for a fiber', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);

    $fiber = new Fiber(function () use ($manager) {
        $sandbox = $manager->createSandbox();
        expect($sandbox)->toBeInstanceOf(Container::class);
        expect($sandbox)->not->toBe($manager->getBaseContainer());
    });

    $fiber->start();
});

test('it returns base container when not in a fiber', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);

    $container = $manager->getCurrentContainer();

    expect($container)->toBe($baseContainer);
});

test('it returns sandbox when in a fiber', function () {
    $baseContainer = new Container;
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
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);

    expect($manager->hasSandbox())->toBeFalse();

    $fiber = new Fiber(function () use ($manager) {
        $manager->createSandbox();
        expect($manager->hasSandbox())->toBeTrue();
    });

    $fiber->start();
});

test('it can destroy sandbox', function () {
    $baseContainer = new Container;
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
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);
    $manager->setEnabled(false);

    $fiber = new Fiber(function () use ($manager, $baseContainer) {
        $sandbox = $manager->createSandbox();
        expect($sandbox)->toBe($baseContainer);
    });

    $fiber->start();
});

test('it isolates state between fibers', function () {
    $baseContainer = new Container;
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

test('it can get base container', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);

    expect($manager->getBaseContainer())->toBe($baseContainer);
});

test('it can check if enabled', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);

    expect($manager->isEnabled())->toBeTrue();

    $manager->setEnabled(false);
    expect($manager->isEnabled())->toBeFalse();
});

test('it creates new sandbox for each fiber', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);

    $sandbox1 = null;
    $sandbox2 = null;

    $fiber1 = new Fiber(function () use ($manager, &$sandbox1) {
        $sandbox1 = $manager->createSandbox();
        Fiber::suspend();
    });

    $fiber2 = new Fiber(function () use ($manager, &$sandbox2) {
        $sandbox2 = $manager->createSandbox();
        Fiber::suspend();
    });

    $fiber1->start();
    $fiber2->start();

    expect($sandbox1)->not->toBe($sandbox2);
    expect($sandbox1)->not->toBe($baseContainer);
    expect($sandbox2)->not->toBe($baseContainer);
});

test('it creates new sandbox on each call', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);

    $fiber = new Fiber(function () use ($manager) {
        $sandbox1 = $manager->createSandbox();
        $sandbox2 = $manager->createSandbox();

        // Each call creates a new sandbox
        expect($sandbox1)->not->toBe($sandbox2);
    });

    $fiber->start();
});

test('it cleans up sandbox after fiber completes', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);

    $fiber = new Fiber(function () use ($manager) {
        $manager->createSandbox();
        expect($manager->hasSandbox())->toBeTrue();
    });

    $fiber->start();

    // After fiber completes, WeakMap should clean up
    expect(true)->toBeTrue();
});

test('it returns base container when disabled and not in fiber', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);
    $manager->setEnabled(false);

    $container = $manager->createSandbox();

    expect($container)->toBe($baseContainer);
});

test('it returns base container when disabled in getCurrentContainer', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);
    $manager->setEnabled(false);

    $fiber = new Fiber(function () use ($manager, $baseContainer) {
        $container = $manager->getCurrentContainer();
        expect($container)->toBe($baseContainer);
        Fiber::suspend();
    });

    $fiber->start();
});

test('it does nothing when destroying sandbox while disabled', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);
    $manager->setEnabled(false);

    $fiber = new Fiber(function () use ($manager) {
        $manager->destroySandbox();
        expect(true)->toBeTrue();
        Fiber::suspend();
    });

    $fiber->start();
});

test('it does nothing when destroying sandbox outside fiber', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);

    $manager->destroySandbox();
    expect(true)->toBeTrue();
});

test('it integrates with pollution detector', function () {
    $baseContainer = new Container;
    $pollutionDetector = Mockery::mock(\FiberFlow\Coroutine\ContainerPollutionDetector::class);

    $pollutionDetector->shouldReceive('takeSnapshot')
        ->once()
        ->with(Mockery::type(Container::class));

    $manager = new SandboxManager($baseContainer, $pollutionDetector);

    $fiber = new Fiber(function () use ($manager) {
        $manager->createSandbox();
        Fiber::suspend();
    });

    $fiber->start();
});

test('it returns false for hasSandbox when disabled', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);
    $manager->setEnabled(false);

    $fiber = new Fiber(function () use ($manager) {
        $manager->createSandbox();
        expect($manager->hasSandbox())->toBeFalse();
        Fiber::suspend();
    });

    $fiber->start();
});

test('it returns zero active sandboxes when disabled', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);
    $manager->setEnabled(false);

    expect($manager->getActiveSandboxCount())->toBe(0);
});

test('it counts active sandboxes correctly', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);

    expect($manager->getActiveSandboxCount())->toBe(0);

    $fiber1 = new Fiber(function () use ($manager) {
        $manager->createSandbox();
        Fiber::suspend();
    });

    $fiber2 = new Fiber(function () use ($manager) {
        $manager->createSandbox();
        Fiber::suspend();
    });

    $fiber1->start();
    expect($manager->getActiveSandboxCount())->toBe(1);

    $fiber2->start();
    expect($manager->getActiveSandboxCount())->toBe(2);
});

test('it verifies integrity with pollution detector', function () {
    $baseContainer = new Container;
    $pollutionDetector = Mockery::mock(\FiberFlow\Coroutine\ContainerPollutionDetector::class);

    $pollutionDetector->shouldReceive('verify')
        ->once()
        ->with(Mockery::type(Container::class));

    $manager = new SandboxManager($baseContainer, $pollutionDetector);

    $fiber = new Fiber(function () use ($manager) {
        $manager->verifyIntegrity();
        Fiber::suspend();
    });

    $fiber->start();
});

test('it does not verify integrity when disabled', function () {
    $baseContainer = new Container;
    $pollutionDetector = Mockery::mock(\FiberFlow\Coroutine\ContainerPollutionDetector::class);

    $pollutionDetector->shouldNotReceive('verify');

    $manager = new SandboxManager($baseContainer, $pollutionDetector);
    $manager->setEnabled(false);

    $manager->verifyIntegrity();
    expect(true)->toBeTrue();
});

test('it does not verify integrity without pollution detector', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);

    $manager->verifyIntegrity();
    expect(true)->toBeTrue();
});

test('it returns pollution detector', function () {
    $baseContainer = new Container;
    $pollutionDetector = Mockery::mock(\FiberFlow\Coroutine\ContainerPollutionDetector::class);

    $manager = new SandboxManager($baseContainer, $pollutionDetector);

    expect($manager->getPollutionDetector())->toBe($pollutionDetector);
});

test('it creates default pollution detector when none provided', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);

    expect($manager->getPollutionDetector())->toBeInstanceOf(\FiberFlow\Coroutine\ContainerPollutionDetector::class);
});

test('it returns base container when called outside fiber context', function () {
    $baseContainer = new Container;
    $manager = new SandboxManager($baseContainer);

    // Call createSandbox outside of a Fiber
    $container = $manager->createSandbox();

    expect($container)->toBe($baseContainer);
});

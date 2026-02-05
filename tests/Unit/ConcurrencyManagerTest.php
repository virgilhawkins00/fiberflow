<?php

declare(strict_types=1);

use FiberFlow\Exceptions\ConcurrencyLimitException;
use FiberFlow\Loop\ConcurrencyManager;

test('it can spawn a fiber', function () {
    $manager = new ConcurrencyManager(maxConcurrency: 10);
    $executed = false;

    $fiber = $manager->spawn(function () use (&$executed) {
        $executed = true;
    });

    expect($fiber)->toBeInstanceOf(Fiber::class);
    expect($executed)->toBeTrue();
});

test('it tracks active fibers', function () {
    $manager = new ConcurrencyManager(maxConcurrency: 10);

    expect($manager->getActiveCount())->toBe(0);

    $manager->spawn(function () {
        Fiber::suspend();
    });

    expect($manager->getActiveCount())->toBe(1);
});

test('it enforces concurrency limit', function () {
    $manager = new ConcurrencyManager(maxConcurrency: 2);

    // Spawn 2 fibers that suspend
    $manager->spawn(function () {
        Fiber::suspend();
    });

    $manager->spawn(function () {
        Fiber::suspend();
    });

    expect($manager->isFull())->toBeTrue();

    // Trying to spawn a third should throw
    expect(fn () => $manager->spawn(function () {
        // This should not execute
    }))->toThrow(ConcurrencyLimitException::class);
});

test('it calculates available slots correctly', function () {
    $manager = new ConcurrencyManager(maxConcurrency: 5);

    expect($manager->getAvailableSlots())->toBe(5);

    $manager->spawn(function () {
        Fiber::suspend();
    });

    expect($manager->getAvailableSlots())->toBe(4);
});

test('it cleans up terminated fibers', function () {
    $manager = new ConcurrencyManager(maxConcurrency: 10);

    // Spawn a fiber that completes immediately
    $manager->spawn(function () {
        // Do nothing, completes immediately
    });

    // Give it a moment to complete
    usleep(1000);

    expect($manager->getActiveCount())->toBe(0);
});

test('it provides metrics', function () {
    $manager = new ConcurrencyManager(maxConcurrency: 10);

    $manager->spawn(function () {
        // Completes immediately
    });

    $metrics = $manager->getMetrics();

    expect($metrics)->toHaveKey('total_spawned');
    expect($metrics)->toHaveKey('total_completed');
    expect($metrics)->toHaveKey('active_fibers');
    expect($metrics['total_spawned'])->toBe(1);
    expect($metrics['total_completed'])->toBe(1);
});

test('it can wait for all fibers to complete', function () {
    $manager = new ConcurrencyManager(maxConcurrency: 10);
    $completed = 0;

    for ($i = 0; $i < 3; $i++) {
        $manager->spawn(function () use (&$completed) {
            usleep(10000); // 10ms
            $completed++;
        });
    }

    $manager->waitForAll();

    expect($completed)->toBe(3);
    expect($manager->getActiveCount())->toBe(0);
});

test('it handles fiber exceptions and tracks failures', function () {
    $manager = new ConcurrencyManager(maxConcurrency: 10);

    try {
        $manager->spawn(function () {
            throw new Exception('Test exception');
        });
    } catch (Exception $e) {
        // Exception is expected to be thrown
    }

    $metrics = $manager->getMetrics();
    expect($metrics['total_failed'])->toBe(1);
});

test('it can spawn multiple fibers concurrently', function () {
    $manager = new ConcurrencyManager(maxConcurrency: 5);
    $results = [];

    for ($i = 0; $i < 5; $i++) {
        $manager->spawn(function () use (&$results, $i) {
            $results[] = $i;
        });
    }

    expect(count($results))->toBe(5);
});

test('it respects concurrency limit with multiple spawns', function () {
    $manager = new ConcurrencyManager(maxConcurrency: 3);

    // Spawn 3 fibers that suspend
    for ($i = 0; $i < 3; $i++) {
        $manager->spawn(function () {
            Fiber::suspend();
        });
    }

    expect($manager->isFull())->toBeTrue();
    expect($manager->getAvailableSlots())->toBe(0);
});

test('it tracks total spawned fibers', function () {
    $manager = new ConcurrencyManager(maxConcurrency: 10);

    for ($i = 0; $i < 5; $i++) {
        $manager->spawn(function () {
            // Completes immediately
        });
    }

    $metrics = $manager->getMetrics();
    expect($metrics['total_spawned'])->toBe(5);
});

test('it can check if not full', function () {
    $manager = new ConcurrencyManager(maxConcurrency: 5);

    expect($manager->isFull())->toBeFalse();

    $manager->spawn(function () {
        Fiber::suspend();
    });

    expect($manager->isFull())->toBeFalse();
});

test('it can remove a fiber', function () {
    $manager = new ConcurrencyManager(maxConcurrency: 10);

    $fiber = $manager->spawn(function () {
        Fiber::suspend();
    });

    expect($manager->getActiveCount())->toBe(1);

    $manager->remove($fiber);

    expect($manager->getActiveCount())->toBe(0);
});

test('it handles removing null fiber gracefully', function () {
    $manager = new ConcurrencyManager(maxConcurrency: 10);

    $manager->remove(null);

    expect($manager->getActiveCount())->toBe(0);
});

test('it can terminate all fibers', function () {
    $manager = new ConcurrencyManager(maxConcurrency: 10);

    for ($i = 0; $i < 3; $i++) {
        $manager->spawn(function () {
            Fiber::suspend();
        });
    }

    expect($manager->getActiveCount())->toBe(3);

    $manager->terminateAll();

    expect($manager->getActiveCount())->toBe(0);
});

test('it gets active fibers list', function () {
    $manager = new ConcurrencyManager(maxConcurrency: 10);

    $fiber1 = $manager->spawn(function () {
        Fiber::suspend();
    });

    $fiber2 = $manager->spawn(function () {
        Fiber::suspend();
    });

    $activeFibers = $manager->getActiveFibers();

    expect($activeFibers)->toHaveCount(2);
    expect($activeFibers)->toContain($fiber1);
    expect($activeFibers)->toContain($fiber2);
});

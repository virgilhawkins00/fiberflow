<?php

declare(strict_types=1);

use FiberFlow\Queue\QueueConcurrencyManager;

test('it uses default limit', function () {
    $manager = new QueueConcurrencyManager(defaultLimit: 50);

    expect($manager->getLimit('default'))->toBe(50);
});

test('it sets custom limits per queue', function () {
    $manager = new QueueConcurrencyManager(defaultLimit: 50);

    $manager->setLimit('high-priority', 100);
    $manager->setLimit('low-priority', 10);

    expect($manager->getLimit('high-priority'))->toBe(100);
    expect($manager->getLimit('low-priority'))->toBe(10);
    expect($manager->getLimit('default'))->toBe(50);
});

test('it tracks active jobs per queue', function () {
    $manager = new QueueConcurrencyManager;

    expect($manager->getActiveCount('default'))->toBe(0);

    $manager->increment('default');
    expect($manager->getActiveCount('default'))->toBe(1);

    $manager->increment('default');
    expect($manager->getActiveCount('default'))->toBe(2);
});

test('it decrements active jobs', function () {
    $manager = new QueueConcurrencyManager;

    $manager->increment('default');
    $manager->increment('default');
    expect($manager->getActiveCount('default'))->toBe(2);

    $manager->decrement('default');
    expect($manager->getActiveCount('default'))->toBe(1);
});

test('it checks if queue can process more jobs', function () {
    $manager = new QueueConcurrencyManager(defaultLimit: 2);

    expect($manager->canProcess('default'))->toBeTrue();

    $manager->increment('default');
    expect($manager->canProcess('default'))->toBeTrue();

    $manager->increment('default');
    expect($manager->canProcess('default'))->toBeFalse(); // At limit
});

test('it calculates available slots', function () {
    $manager = new QueueConcurrencyManager(defaultLimit: 10);

    expect($manager->getAvailableSlots('default'))->toBe(10);

    $manager->increment('default');
    $manager->increment('default');
    $manager->increment('default');

    expect($manager->getAvailableSlots('default'))->toBe(7);
});

test('it handles multiple queues independently', function () {
    $manager = new QueueConcurrencyManager(defaultLimit: 5);

    $manager->setLimit('queue-a', 10);
    $manager->setLimit('queue-b', 3);

    $manager->increment('queue-a');
    $manager->increment('queue-a');
    $manager->increment('queue-b');

    expect($manager->getActiveCount('queue-a'))->toBe(2);
    expect($manager->getActiveCount('queue-b'))->toBe(1);
    expect($manager->getAvailableSlots('queue-a'))->toBe(8);
    expect($manager->getAvailableSlots('queue-b'))->toBe(2);
});

test('it gets all active counts', function () {
    $manager = new QueueConcurrencyManager;

    $manager->increment('queue-a');
    $manager->increment('queue-a');
    $manager->increment('queue-b');

    $counts = $manager->getAllActiveCounts();

    expect($counts)->toBe([
        'queue-a' => 2,
        'queue-b' => 1,
    ]);
});

test('it gets all limits', function () {
    $manager = new QueueConcurrencyManager(defaultLimit: 50);

    $manager->setLimit('queue-a', 100);
    $manager->setLimit('queue-b', 25);

    $limits = $manager->getAllLimits();

    expect($limits)->toBe([
        'queue-a' => 100,
        'queue-b' => 25,
    ]);
});

test('it resets all counters', function () {
    $manager = new QueueConcurrencyManager;

    $manager->increment('queue-a');
    $manager->increment('queue-b');

    $manager->reset();

    expect($manager->getAllActiveCounts())->toBeEmpty();
});

test('it resets specific queue', function () {
    $manager = new QueueConcurrencyManager;

    $manager->increment('queue-a');
    $manager->increment('queue-b');

    $manager->resetQueue('queue-a');

    expect($manager->getActiveCount('queue-a'))->toBe(0);
    expect($manager->getActiveCount('queue-b'))->toBe(1);
});

test('it does not decrement below zero', function () {
    $manager = new QueueConcurrencyManager;

    $manager->decrement('default');
    $manager->decrement('default');

    expect($manager->getActiveCount('default'))->toBe(0);
});

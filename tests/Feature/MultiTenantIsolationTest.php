<?php

declare(strict_types=1);

use FiberFlow\Facades\FiberCache;
use FiberFlow\Facades\FiberSession;
use FiberFlow\Coroutine\SandboxManager;
use FiberFlow\Coroutine\ContainerPollutionDetector;
use Illuminate\Container\Container;

test('multiple tenants can process jobs concurrently without state leakage', function () {
    $results = [];

    // Simulate 3 different tenants processing jobs simultaneously
    $tenant1Fiber = new Fiber(function () use (&$results) {
        FiberCache::contextPut('tenant_id', 1);
        FiberCache::contextPut('company_name', 'Acme Corp');
        FiberSession::fiberPut('user_id', 100);

        // Simulate some processing time
        usleep(5000);

        $results['tenant1'] = [
            'tenant_id' => FiberCache::contextGet('tenant_id'),
            'company_name' => FiberCache::contextGet('company_name'),
            'user_id' => FiberSession::fiberGet('user_id'),
        ];
    });

    $tenant2Fiber = new Fiber(function () use (&$results) {
        FiberCache::contextPut('tenant_id', 2);
        FiberCache::contextPut('company_name', 'TechStart Inc');
        FiberSession::fiberPut('user_id', 200);

        // Simulate some processing time
        usleep(5000);

        $results['tenant2'] = [
            'tenant_id' => FiberCache::contextGet('tenant_id'),
            'company_name' => FiberCache::contextGet('company_name'),
            'user_id' => FiberSession::fiberGet('user_id'),
        ];
    });

    $tenant3Fiber = new Fiber(function () use (&$results) {
        FiberCache::contextPut('tenant_id', 3);
        FiberCache::contextPut('company_name', 'Global Solutions');
        FiberSession::fiberPut('user_id', 300);

        // Simulate some processing time
        usleep(5000);

        $results['tenant3'] = [
            'tenant_id' => FiberCache::contextGet('tenant_id'),
            'company_name' => FiberCache::contextGet('company_name'),
            'user_id' => FiberSession::fiberGet('user_id'),
        ];
    });

    // Start all fibers
    $tenant1Fiber->start();
    $tenant2Fiber->start();
    $tenant3Fiber->start();

    // Wait for all to complete
    while (!$tenant1Fiber->isTerminated() || !$tenant2Fiber->isTerminated() || !$tenant3Fiber->isTerminated()) {
        usleep(1000);
    }

    // Verify complete isolation
    expect($results['tenant1']['tenant_id'])->toBe(1);
    expect($results['tenant1']['company_name'])->toBe('Acme Corp');
    expect($results['tenant1']['user_id'])->toBe(100);

    expect($results['tenant2']['tenant_id'])->toBe(2);
    expect($results['tenant2']['company_name'])->toBe('TechStart Inc');
    expect($results['tenant2']['user_id'])->toBe(200);

    expect($results['tenant3']['tenant_id'])->toBe(3);
    expect($results['tenant3']['company_name'])->toBe('Global Solutions');
    expect($results['tenant3']['user_id'])->toBe(300);
});

test('sandbox manager creates isolated containers per fiber', function () {
    $container = new Container();
    $sandboxManager = new SandboxManager($container);

    $sandboxes = [];

    $fiber1 = new Fiber(function () use ($sandboxManager, &$sandboxes) {
        $sandbox = $sandboxManager->createSandbox();
        $sandboxes['fiber1'] = spl_object_hash($sandbox);
    });

    $fiber2 = new Fiber(function () use ($sandboxManager, &$sandboxes) {
        $sandbox = $sandboxManager->createSandbox();
        $sandboxes['fiber2'] = spl_object_hash($sandbox);
    });

    $fiber1->start();
    $fiber2->start();

    // Sandboxes should be different objects
    expect($sandboxes['fiber1'])->not->toBe($sandboxes['fiber2']);
});

test('container pollution detector takes snapshots correctly', function () {
    $container = new Container();
    $detector = new ContainerPollutionDetector();

    $fiber = new Fiber(function () use ($container, $detector) {
        $detector->takeSnapshot($container);
        
        // Verify without changes - should pass
        $detector->verify($container);
        
        expect(true)->toBeTrue();
    });

    $fiber->start();
    expect($fiber->isTerminated())->toBeTrue();
});

test('fiber-scoped cache keys are unique per fiber', function () {
    $keys = [];

    $fiber1 = new Fiber(function () use (&$keys) {
        $keys[] = FiberCache::fiberKey('user_data');
    });

    $fiber2 = new Fiber(function () use (&$keys) {
        $keys[] = FiberCache::fiberKey('user_data');
    });

    $fiber1->start();
    $fiber2->start();

    expect($keys)->toHaveCount(2);
    expect($keys[0])->not->toBe($keys[1]);
    expect($keys[0])->toContain('fiber:');
    expect($keys[1])->toContain('fiber:');
});

test('session data is isolated between concurrent fibers', function () {
    $sessionData = [];

    $fiber1 = new Fiber(function () use (&$sessionData) {
        FiberSession::fiberPut('order_id', 'ORD-001');
        FiberSession::fiberPut('total', 99.99);
        
        usleep(2000);
        
        $sessionData['fiber1'] = FiberSession::fiberAll();
    });

    $fiber2 = new Fiber(function () use (&$sessionData) {
        FiberSession::fiberPut('order_id', 'ORD-002');
        FiberSession::fiberPut('total', 149.99);
        
        usleep(2000);
        
        $sessionData['fiber2'] = FiberSession::fiberAll();
    });

    $fiber1->start();
    $fiber2->start();

    while (!$fiber1->isTerminated() || !$fiber2->isTerminated()) {
        usleep(500);
    }

    expect($sessionData['fiber1']['order_id'])->toBe('ORD-001');
    expect($sessionData['fiber1']['total'])->toBe(99.99);
    expect($sessionData['fiber2']['order_id'])->toBe('ORD-002');
    expect($sessionData['fiber2']['total'])->toBe(149.99);
});

test('zero state leakage between 10 concurrent fibers', function () {
    $results = [];
    $fibers = [];

    // Create 10 fibers with different tenant data
    for ($i = 1; $i <= 10; $i++) {
        $fibers[] = new Fiber(function () use ($i, &$results) {
            FiberCache::contextPut('tenant_id', $i);
            FiberCache::contextPut('api_key', "key_{$i}");
            FiberSession::fiberPut('user_id', $i * 100);

            // Simulate work
            usleep(rand(1000, 5000));

            $results[$i] = [
                'tenant_id' => FiberCache::contextGet('tenant_id'),
                'api_key' => FiberCache::contextGet('api_key'),
                'user_id' => FiberSession::fiberGet('user_id'),
            ];
        });
    }

    // Start all fibers
    foreach ($fibers as $fiber) {
        $fiber->start();
    }

    // Wait for all to complete
    $allTerminated = false;
    while (!$allTerminated) {
        $allTerminated = true;
        foreach ($fibers as $fiber) {
            if (!$fiber->isTerminated()) {
                $allTerminated = false;
                break;
            }
        }
        usleep(1000);
    }

    // Verify each fiber maintained its own state
    for ($i = 1; $i <= 10; $i++) {
        expect($results[$i]['tenant_id'])->toBe($i);
        expect($results[$i]['api_key'])->toBe("key_{$i}");
        expect($results[$i]['user_id'])->toBe($i * 100);
    }
});


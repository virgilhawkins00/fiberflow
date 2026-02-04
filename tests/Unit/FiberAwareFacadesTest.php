<?php

declare(strict_types=1);

use FiberFlow\Facades\FiberAuth;
use FiberFlow\Facades\FiberCache;
use FiberFlow\Facades\FiberSession;
use FiberFlow\Coroutine\FiberContext;
use FiberFlow\Coroutine\SandboxManager;

beforeEach(function () {
    // Register SandboxManager in container
    app()->singleton('fiberflow.sandbox', function ($app) {
        return new SandboxManager($app);
    });
});

test('FiberCache can store and retrieve fiber-scoped values', function () {
    $fiber1 = new Fiber(function () {
        FiberCache::fiberPut('test_key', 'fiber1_value');
        expect(FiberCache::fiberGet('test_key'))->toBe('fiber1_value');
    });

    $fiber2 = new Fiber(function () {
        FiberCache::fiberPut('test_key', 'fiber2_value');
        expect(FiberCache::fiberGet('test_key'))->toBe('fiber2_value');
    });

    $fiber1->start();
    $fiber2->start();

    // Values should be isolated between fibers
    expect($fiber1->isTerminated())->toBeTrue();
    expect($fiber2->isTerminated())->toBeTrue();
});

test('FiberCache generates unique keys per fiber', function () {
    $keys = [];

    $fiber1 = new Fiber(function () use (&$keys) {
        $keys[] = FiberCache::fiberKey('test');
    });

    $fiber2 = new Fiber(function () use (&$keys) {
        $keys[] = FiberCache::fiberKey('test');
    });

    $fiber1->start();
    $fiber2->start();

    expect($keys)->toHaveCount(2);
    expect($keys[0])->not->toBe($keys[1]);
    expect($keys[0])->toContain('fiber:');
    expect($keys[1])->toContain('fiber:');
});

test('FiberCache context methods work correctly', function () {
    $fiber = new Fiber(function () {
        FiberCache::contextPut('user_id', 123);
        expect(FiberCache::contextHas('user_id'))->toBeTrue();
        expect(FiberCache::contextGet('user_id'))->toBe(123);
        expect(FiberCache::contextGet('missing', 'default'))->toBe('default');
    });

    $fiber->start();
    expect($fiber->isTerminated())->toBeTrue();
});

test('FiberSession can store and retrieve fiber-local values', function () {
    $fiber1 = new Fiber(function () {
        FiberSession::fiberPut('user_id', 1);
        expect(FiberSession::fiberGet('user_id'))->toBe(1);
        expect(FiberSession::fiberHas('user_id'))->toBeTrue();
    });

    $fiber2 = new Fiber(function () {
        FiberSession::fiberPut('user_id', 2);
        expect(FiberSession::fiberGet('user_id'))->toBe(2);
        expect(FiberSession::fiberHas('user_id'))->toBeTrue();
    });

    $fiber1->start();
    $fiber2->start();

    expect($fiber1->isTerminated())->toBeTrue();
    expect($fiber2->isTerminated())->toBeTrue();
});

test('FiberSession can retrieve all fiber-local data', function () {
    $fiber = new Fiber(function () {
        FiberSession::fiberPut('key1', 'value1');
        FiberSession::fiberPut('key2', 'value2');
        
        $all = FiberSession::fiberAll();
        
        expect($all)->toHaveKey('key1');
        expect($all)->toHaveKey('key2');
        expect($all['key1'])->toBe('value1');
        expect($all['key2'])->toBe('value2');
    });

    $fiber->start();
    expect($fiber->isTerminated())->toBeTrue();
});

test('FiberSession can forget fiber-local values', function () {
    $fiber = new Fiber(function () {
        FiberSession::fiberPut('temp_key', 'temp_value');
        expect(FiberSession::fiberHas('temp_key'))->toBeTrue();
        
        FiberSession::fiberForget('temp_key');
        expect(FiberSession::fiberHas('temp_key'))->toBeFalse();
    });

    $fiber->start();
    expect($fiber->isTerminated())->toBeTrue();
});

test('FiberSession can clear all fiber-local data', function () {
    $fiber = new Fiber(function () {
        FiberSession::fiberPut('key1', 'value1');
        FiberSession::fiberPut('key2', 'value2');
        
        expect(FiberSession::fiberAll())->toHaveCount(2);
        
        FiberSession::clearFiberSession();
        
        expect(FiberSession::fiberAll())->toHaveCount(0);
    });

    $fiber->start();
    expect($fiber->isTerminated())->toBeTrue();
});

test('FiberSession flash data works correctly', function () {
    $fiber = new Fiber(function () {
        FiberSession::fiberFlash('message', 'Success!');
        expect(FiberContext::has('session.flash.message'))->toBeTrue();
        expect(FiberContext::get('session.flash.message'))->toBe('Success!');
    });

    $fiber->start();
    expect($fiber->isTerminated())->toBeTrue();
});

test('fiber-aware facades isolate state between fibers', function () {
    $results = [];

    $fiber1 = new Fiber(function () use (&$results) {
        FiberCache::contextPut('tenant_id', 'tenant_1');
        FiberSession::fiberPut('user_id', 100);
        
        // Simulate some work
        usleep(1000);
        
        $results['fiber1'] = [
            'tenant' => FiberCache::contextGet('tenant_id'),
            'user' => FiberSession::fiberGet('user_id'),
        ];
    });

    $fiber2 = new Fiber(function () use (&$results) {
        FiberCache::contextPut('tenant_id', 'tenant_2');
        FiberSession::fiberPut('user_id', 200);
        
        // Simulate some work
        usleep(1000);
        
        $results['fiber2'] = [
            'tenant' => FiberCache::contextGet('tenant_id'),
            'user' => FiberSession::fiberGet('user_id'),
        ];
    });

    $fiber1->start();
    $fiber2->start();

    // Wait for completion
    while (!$fiber1->isTerminated() || !$fiber2->isTerminated()) {
        usleep(100);
    }

    // Verify isolation
    expect($results['fiber1']['tenant'])->toBe('tenant_1');
    expect($results['fiber1']['user'])->toBe(100);
    expect($results['fiber2']['tenant'])->toBe('tenant_2');
    expect($results['fiber2']['user'])->toBe(200);
});


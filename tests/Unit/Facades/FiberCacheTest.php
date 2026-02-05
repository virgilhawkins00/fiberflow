<?php

declare(strict_types=1);

use FiberFlow\Facades\FiberCache;

it('generates fiber-scoped cache keys', function () {
    $fiber = new Fiber(function () {
        $key = FiberCache::fiberKey('test');
        expect($key)->toContain('fiber:');
        expect($key)->toContain(':test');
        Fiber::suspend();
    });

    $fiber->start();
});

it('returns plain key when not in fiber', function () {
    $key = FiberCache::fiberKey('test');
    expect($key)->toBe('test');
});

it('can store and retrieve from context', function () {
    $fiber = new Fiber(function () {
        FiberCache::contextPut('test', 'value');
        $value = FiberCache::contextGet('test');
        expect($value)->toBe('value');
        Fiber::suspend();
    });

    $fiber->start();
});

it('can check if context has key', function () {
    $fiber = new Fiber(function () {
        FiberCache::contextPut('test', 'value');
        expect(FiberCache::contextHas('test'))->toBeTrue();
        expect(FiberCache::contextHas('nonexistent'))->toBeFalse();
        Fiber::suspend();
    });

    $fiber->start();
});

it('returns default value when context key missing', function () {
    $fiber = new Fiber(function () {
        $value = FiberCache::contextGet('nonexistent', 'default');
        expect($value)->toBe('default');
        Fiber::suspend();
    });

    $fiber->start();
});

it('isolates context between fibers', function () {
    $value1 = null;
    $value2 = null;

    $fiber1 = new Fiber(function () use (&$value1) {
        FiberCache::contextPut('test', 'fiber1');
        $value1 = FiberCache::contextGet('test');
        Fiber::suspend();
    });

    $fiber2 = new Fiber(function () use (&$value2) {
        FiberCache::contextPut('test', 'fiber2');
        $value2 = FiberCache::contextGet('test');
        Fiber::suspend();
    });

    $fiber1->start();
    $fiber2->start();

    expect($value1)->toBe('fiber1');
    expect($value2)->toBe('fiber2');
});

it('generates unique keys for different fibers', function () {
    $key1 = null;
    $key2 = null;

    $fiber1 = new Fiber(function () use (&$key1) {
        $key1 = FiberCache::fiberKey('test');
        Fiber::suspend();
    });

    $fiber2 = new Fiber(function () use (&$key2) {
        $key2 = FiberCache::fiberKey('test');
        Fiber::suspend();
    });

    $fiber1->start();
    $fiber2->start();

    expect($key1)->not->toBe($key2);
    expect($key1)->toContain(':test');
    expect($key2)->toContain(':test');
});

it('can flush fiber cache', function () {
    $fiber = new Fiber(function () {
        $result = FiberCache::flushFiberCache();
        expect($result)->toBeTrue();
        Fiber::suspend();
    });

    $fiber->start();
});

it('returns false when flushing outside fiber', function () {
    $result = FiberCache::flushFiberCache();
    expect($result)->toBeFalse();
});

it('can store multiple values in context', function () {
    $fiber = new Fiber(function () {
        FiberCache::contextPut('key1', 'value1');
        FiberCache::contextPut('key2', 'value2');
        FiberCache::contextPut('key3', 'value3');

        expect(FiberCache::contextGet('key1'))->toBe('value1');
        expect(FiberCache::contextGet('key2'))->toBe('value2');
        expect(FiberCache::contextGet('key3'))->toBe('value3');
        Fiber::suspend();
    });

    $fiber->start();
});

it('resolves facade accessor correctly', function () {
    $accessor = (new ReflectionClass(FiberCache::class))
        ->getMethod('getFacadeAccessor')
        ->invoke(null);

    expect($accessor)->toBe('fiberflow.cache');
});

it('can overwrite context values', function () {
    $fiber = new Fiber(function () {
        FiberCache::contextPut('key', 'value1');
        expect(FiberCache::contextGet('key'))->toBe('value1');

        FiberCache::contextPut('key', 'value2');
        expect(FiberCache::contextGet('key'))->toBe('value2');
        Fiber::suspend();
    });

    $fiber->start();
});

it('context returns null for missing keys without default', function () {
    $fiber = new Fiber(function () {
        $value = FiberCache::contextGet('nonexistent');
        expect($value)->toBeNull();
        Fiber::suspend();
    });

    $fiber->start();
});

<?php

declare(strict_types=1);

use FiberFlow\Facades\FiberSession;

it('can store and retrieve fiber session data', function () {
    $fiber = new Fiber(function () {
        FiberSession::fiberPut('test', 'value');
        $value = FiberSession::fiberGet('test');
        expect($value)->toBe('value');
        Fiber::suspend();
    });

    $fiber->start();
});

it('can check if fiber session has key', function () {
    $fiber = new Fiber(function () {
        FiberSession::fiberPut('test', 'value');
        expect(FiberSession::fiberHas('test'))->toBeTrue();
        expect(FiberSession::fiberHas('nonexistent'))->toBeFalse();
        Fiber::suspend();
    });

    $fiber->start();
});

it('can forget fiber session data', function () {
    $fiber = new Fiber(function () {
        FiberSession::fiberPut('test', 'value');
        expect(FiberSession::fiberHas('test'))->toBeTrue();

        FiberSession::fiberForget('test');
        expect(FiberSession::fiberHas('test'))->toBeFalse();
        Fiber::suspend();
    });

    $fiber->start();
});

it('returns default value when key missing', function () {
    $fiber = new Fiber(function () {
        $value = FiberSession::fiberGet('nonexistent', 'default');
        expect($value)->toBe('default');
        Fiber::suspend();
    });

    $fiber->start();
});

it('isolates session data between fibers', function () {
    $value1 = null;
    $value2 = null;

    $fiber1 = new Fiber(function () use (&$value1) {
        FiberSession::fiberPut('test', 'fiber1');
        $value1 = FiberSession::fiberGet('test');
        Fiber::suspend();
    });

    $fiber2 = new Fiber(function () use (&$value2) {
        FiberSession::fiberPut('test', 'fiber2');
        $value2 = FiberSession::fiberGet('test');
        Fiber::suspend();
    });

    $fiber1->start();
    $fiber2->start();

    expect($value1)->toBe('fiber1');
    expect($value2)->toBe('fiber2');
});

it('can get all fiber session data', function () {
    $fiber = new Fiber(function () {
        FiberSession::fiberPut('key1', 'value1');
        FiberSession::fiberPut('key2', 'value2');

        $all = FiberSession::fiberAll();

        expect($all)->toHaveKey('key1');
        expect($all)->toHaveKey('key2');
        expect($all['key1'])->toBe('value1');
        expect($all['key2'])->toBe('value2');
        Fiber::suspend();
    });

    $fiber->start();
});

it('can flash data to fiber session', function () {
    $fiber = new Fiber(function () {
        FiberSession::fiberFlash('flash_key', 'flash_value');
        expect(FiberSession::fiberHas('flash.flash_key'))->toBeTrue();
        Fiber::suspend();
    });

    $fiber->start();
});

it('can clear all fiber session data', function () {
    $fiber = new Fiber(function () {
        FiberSession::fiberPut('key1', 'value1');
        FiberSession::fiberPut('key2', 'value2');

        FiberSession::clearFiberSession();

        expect(FiberSession::fiberHas('key1'))->toBeFalse();
        expect(FiberSession::fiberHas('key2'))->toBeFalse();
        Fiber::suspend();
    });

    $fiber->start();
});

it('can store multiple values in fiber session', function () {
    $fiber = new Fiber(function () {
        FiberSession::fiberPut('key1', 'value1');
        FiberSession::fiberPut('key2', 'value2');
        FiberSession::fiberPut('key3', 'value3');

        expect(FiberSession::fiberGet('key1'))->toBe('value1');
        expect(FiberSession::fiberGet('key2'))->toBe('value2');
        expect(FiberSession::fiberGet('key3'))->toBe('value3');
        Fiber::suspend();
    });

    $fiber->start();
});

it('handles session operations outside fiber', function () {
    // These should not throw errors when called outside a fiber
    FiberSession::fiberGet('test', 'default');
    FiberSession::fiberHas('test');
    FiberSession::clearFiberSession();

    expect(true)->toBeTrue();
});

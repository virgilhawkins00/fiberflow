<?php

declare(strict_types=1);

use FiberFlow\Coroutine\FiberContext;

test('it can set and get values in fiber context', function () {
    $fiber = new Fiber(function () {
        FiberContext::set('key', 'value');
        $value = FiberContext::get('key');

        expect($value)->toBe('value');
    });

    $fiber->start();
});

test('it returns default when key does not exist', function () {
    $fiber = new Fiber(function () {
        $value = FiberContext::get('nonexistent', 'default');
        expect($value)->toBe('default');
    });

    $fiber->start();
});

test('it can check if key exists', function () {
    $fiber = new Fiber(function () {
        expect(FiberContext::has('key'))->toBeFalse();

        FiberContext::set('key', 'value');

        expect(FiberContext::has('key'))->toBeTrue();
    });

    $fiber->start();
});

test('it can forget values', function () {
    $fiber = new Fiber(function () {
        FiberContext::set('key', 'value');
        expect(FiberContext::has('key'))->toBeTrue();

        FiberContext::forget('key');
        expect(FiberContext::has('key'))->toBeFalse();
    });

    $fiber->start();
});

test('it can get all context data', function () {
    $fiber = new Fiber(function () {
        FiberContext::set('key1', 'value1');
        FiberContext::set('key2', 'value2');

        $all = FiberContext::all();

        expect($all)->toBe([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
    });

    $fiber->start();
});

test('it can clear all context data', function () {
    $fiber = new Fiber(function () {
        FiberContext::set('key1', 'value1');
        FiberContext::set('key2', 'value2');

        FiberContext::clear();

        expect(FiberContext::all())->toBe([]);
    });

    $fiber->start();
});

test('it isolates context between fibers', function () {
    $value1 = null;
    $value2 = null;

    $fiber1 = new Fiber(function () use (&$value1) {
        FiberContext::set('user', 'Alice');
        $value1 = FiberContext::get('user');
    });

    $fiber2 = new Fiber(function () use (&$value2) {
        FiberContext::set('user', 'Bob');
        $value2 = FiberContext::get('user');
    });

    $fiber1->start();
    $fiber2->start();

    expect($value1)->toBe('Alice');
    expect($value2)->toBe('Bob');
});


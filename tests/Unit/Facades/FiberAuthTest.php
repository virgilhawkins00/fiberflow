<?php

declare(strict_types=1);

use FiberFlow\Coroutine\FiberContext;
use FiberFlow\Facades\FiberAuth;
use Illuminate\Contracts\Auth\Authenticatable;

it('resolves facade accessor', function () {
    $accessor = (new \ReflectionClass(FiberAuth::class))
        ->getMethod('getFacadeAccessor')
        ->invoke(null);

    expect($accessor)->toBe('fiberflow.auth');
});

it('has setFiberUser method', function () {
    $reflection = new ReflectionClass(FiberAuth::class);
    expect($reflection->hasMethod('setFiberUser'))->toBeTrue();
});

it('has getFiberUser method', function () {
    $reflection = new ReflectionClass(FiberAuth::class);
    expect($reflection->hasMethod('getFiberUser'))->toBeTrue();
});

it('has fiberCheck method', function () {
    $reflection = new ReflectionClass(FiberAuth::class);
    expect($reflection->hasMethod('fiberCheck'))->toBeTrue();
});

it('has clearFiberAuth method', function () {
    $reflection = new ReflectionClass(FiberAuth::class);
    expect($reflection->hasMethod('clearFiberAuth'))->toBeTrue();
});

it('can store user in fiber context', function () {
    $user = Mockery::mock(Authenticatable::class);

    $result = null;
    $fiber = new Fiber(function () use ($user, &$result) {
        FiberContext::set('auth.user', $user);
        $result = FiberContext::has('auth.user');
        Fiber::suspend();
    });

    $fiber->start();

    expect($result)->toBeTrue();
});

it('isolates user data between fibers', function () {
    $user1 = Mockery::mock(Authenticatable::class);
    $user1->shouldReceive('getAuthIdentifier')->andReturn(1);

    $user2 = Mockery::mock(Authenticatable::class);
    $user2->shouldReceive('getAuthIdentifier')->andReturn(2);

    $id1 = null;
    $id2 = null;

    $fiber1 = new Fiber(function () use ($user1, &$id1) {
        FiberContext::set('auth.user', $user1);
        $id1 = FiberContext::get('auth.user')?->getAuthIdentifier();
        Fiber::suspend();
    });

    $fiber2 = new Fiber(function () use ($user2, &$id2) {
        FiberContext::set('auth.user', $user2);
        $id2 = FiberContext::get('auth.user')?->getAuthIdentifier();
        Fiber::suspend();
    });

    $fiber1->start();
    $fiber2->start();

    expect($id1)->toBe(1)
        ->and($id2)->toBe(2);
});

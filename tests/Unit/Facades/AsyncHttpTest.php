<?php

declare(strict_types=1);

use FiberFlow\Facades\AsyncHttp;

it('resolves facade accessor', function () {
    $accessor = (new \ReflectionClass(AsyncHttp::class))
        ->getMethod('getFacadeAccessor')
        ->invoke(null);

    expect($accessor)->toBe('fiberflow.http');
});

it('can access facade in fiber context', function () {
    $fiber = new Fiber(function () {
        $accessor = (new \ReflectionClass(AsyncHttp::class))
            ->getMethod('getFacadeAccessor')
            ->invoke(null);

        expect($accessor)->toBe('fiberflow.http');
        Fiber::suspend();
    });

    $fiber->start();
});

it('resolves facade instance correctly', function () {
    // Test that the facade can be resolved
    $accessor = (new \ReflectionClass(AsyncHttp::class))
        ->getMethod('getFacadeAccessor')
        ->invoke(null);

    expect($accessor)->toBeString();
    expect($accessor)->toBe('fiberflow.http');
});

it('maintains facade accessor across multiple calls', function () {
    $accessor1 = (new \ReflectionClass(AsyncHttp::class))
        ->getMethod('getFacadeAccessor')
        ->invoke(null);

    $accessor2 = (new \ReflectionClass(AsyncHttp::class))
        ->getMethod('getFacadeAccessor')
        ->invoke(null);

    expect($accessor1)->toBe($accessor2);
});

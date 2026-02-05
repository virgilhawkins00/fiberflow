<?php

declare(strict_types=1);

use FiberFlow\Facades\AsyncHttp;

it('resolves facade accessor', function () {
    $accessor = (new \ReflectionClass(AsyncHttp::class))
        ->getMethod('getFacadeAccessor')
        ->invoke(null);

    expect($accessor)->toBe('fiberflow.http');
});

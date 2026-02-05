<?php

declare(strict_types=1);

use FiberFlow\Facades\FiberAuth;
use Illuminate\Contracts\Auth\Authenticatable;

it('resolves facade accessor', function () {
    $accessor = (new \ReflectionClass(FiberAuth::class))
        ->getMethod('getFacadeAccessor')
        ->invoke(null);

    expect($accessor)->toBe('fiberflow.auth');
});




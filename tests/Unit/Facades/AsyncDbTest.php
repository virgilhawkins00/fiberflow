<?php

declare(strict_types=1);

use FiberFlow\Database\AsyncQueryBuilder;
use FiberFlow\Facades\AsyncDb;

it('creates query builder for table', function () {
    $builder = AsyncDb::table('users');

    expect($builder)->toBeInstanceOf(AsyncQueryBuilder::class);
});

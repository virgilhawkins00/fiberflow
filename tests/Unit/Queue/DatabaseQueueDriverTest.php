<?php

declare(strict_types=1);

use FiberFlow\Database\AsyncDbConnection;
use FiberFlow\Queue\Drivers\DatabaseQueueDriver;

it('initializes with connection', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    expect($driver)->toBeInstanceOf(DatabaseQueueDriver::class);
});

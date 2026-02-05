<?php

declare(strict_types=1);

use FiberFlow\Queue\Drivers\RabbitMqQueueDriver;

it('initializes with configuration', function () {
    $config = [
        'host' => 'localhost',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
        'exchange' => 'fiberflow',
    ];

    $driver = new RabbitMqQueueDriver($config);

    expect($driver)->toBeInstanceOf(RabbitMqQueueDriver::class);
});


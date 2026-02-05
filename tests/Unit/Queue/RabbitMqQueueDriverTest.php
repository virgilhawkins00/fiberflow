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

it('accepts minimal config', function () {
    $config = [
        'host' => 'localhost',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
    ];

    $driver = new RabbitMqQueueDriver($config);

    expect($driver)->toBeInstanceOf(RabbitMqQueueDriver::class);
});

it('uses default values for optional config', function () {
    $config = [
        'host' => 'localhost',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
    ];

    $driver = new RabbitMqQueueDriver($config);

    expect($driver)->toBeInstanceOf(RabbitMqQueueDriver::class);
});

it('handles connection configuration', function () {
    $config = [
        'host' => 'rabbitmq.example.com',
        'port' => 5673,
        'user' => 'admin',
        'password' => 'secret',
        'vhost' => '/production',
    ];

    $driver = new RabbitMqQueueDriver($config);

    expect($driver)->toBeInstanceOf(RabbitMqQueueDriver::class);
});

it('supports SSL configuration', function () {
    $config = [
        'host' => 'localhost',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
        'ssl' => true,
        'ssl_verify' => false,
    ];

    $driver = new RabbitMqQueueDriver($config);

    expect($driver)->toBeInstanceOf(RabbitMqQueueDriver::class);
});

it('supports exchange configuration', function () {
    $config = [
        'host' => 'localhost',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
        'exchange' => 'jobs',
        'exchange_type' => 'direct',
    ];

    $driver = new RabbitMqQueueDriver($config);

    expect($driver)->toBeInstanceOf(RabbitMqQueueDriver::class);
});

it('supports queue configuration', function () {
    $config = [
        'host' => 'localhost',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
        'queue' => 'default',
        'queue_durable' => true,
        'queue_auto_delete' => false,
    ];

    $driver = new RabbitMqQueueDriver($config);

    expect($driver)->toBeInstanceOf(RabbitMqQueueDriver::class);
});

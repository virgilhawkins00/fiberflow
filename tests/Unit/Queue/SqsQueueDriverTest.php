<?php

declare(strict_types=1);

use FiberFlow\Queue\Drivers\SqsQueueDriver;

it('initializes with configuration', function () {
    $config = [
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'us-east-1',
        'queue_url' => 'https://sqs.us-east-1.amazonaws.com/123456789/test-queue',
    ];

    $driver = new SqsQueueDriver($config);

    expect($driver)->toBeInstanceOf(SqsQueueDriver::class);
});

it('validates configuration on instantiation', function () {
    $config = [
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'us-east-1',
        'queue' => 'https://sqs.us-east-1.amazonaws.com/123456789/test-queue',
    ];

    $driver = new SqsQueueDriver($config);

    expect($driver)->toBeInstanceOf(SqsQueueDriver::class);
});

it('supports different AWS regions', function () {
    $config = [
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'eu-west-1',
        'queue' => 'https://sqs.eu-west-1.amazonaws.com/123456789/test-queue',
    ];

    $driver = new SqsQueueDriver($config);

    expect($driver)->toBeInstanceOf(SqsQueueDriver::class);
});

it('supports custom queue URLs', function () {
    $config = [
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'us-east-1',
        'queue' => 'https://sqs.us-east-1.amazonaws.com/987654321/custom-queue',
    ];

    $driver = new SqsQueueDriver($config);

    expect($driver)->toBeInstanceOf(SqsQueueDriver::class);
});

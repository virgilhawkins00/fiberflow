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

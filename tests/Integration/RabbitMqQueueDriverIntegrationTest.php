<?php

declare(strict_types=1);

use FiberFlow\Queue\Drivers\RabbitMqQueueDriver;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

uses()->group('integration', 'rabbitmq');

beforeEach(function () {
    // Configure RabbitMQ connection
    config()->set('queue.connections.rabbitmq', [
        'driver' => 'rabbitmq',
        'host' => '127.0.0.1',
        'port' => 5673,
        'user' => 'fiberflow',
        'password' => 'fiberflow',
        'vhost' => '/',
        'queue' => 'fiberflow_test',
    ]);

    $this->driver = new RabbitMqQueueDriver('rabbitmq');
    
    // Clean up queue before each test
    try {
        $connection = new AMQPStreamConnection(
            '127.0.0.1',
            5673,
            'fiberflow',
            'fiberflow',
            '/'
        );
        $channel = $connection->channel();
        $channel->queue_purge('fiberflow_test');
        $channel->close();
        $connection->close();
    } catch (\Throwable $e) {
        // Queue might not exist yet
    }
});

afterEach(function () {
    // Clean up
    try {
        $connection = new AMQPStreamConnection(
            '127.0.0.1',
            5673,
            'fiberflow',
            'fiberflow',
            '/'
        );
        $channel = $connection->channel();
        $channel->queue_delete('fiberflow_test');
        $channel->close();
        $connection->close();
    } catch (\Throwable $e) {
        // Ignore cleanup errors
    }
});

test('it can connect to RabbitMQ', function () {
    expect($this->driver)->toBeInstanceOf(RabbitMqQueueDriver::class);
});

test('it can push jobs to queue', function () {
    $job = new class
    {
        public function handle()
        {
            return 'test';
        }
    };
    
    $this->driver->push($job, 'test-data', 'fiberflow_test');
    
    // Verify job was pushed by checking queue size
    $connection = new AMQPStreamConnection(
        '127.0.0.1',
        5673,
        'fiberflow',
        'fiberflow',
        '/'
    );
    $channel = $connection->channel();
    
    list($queue, $messageCount, $consumerCount) = $channel->queue_declare('fiberflow_test', true);
    
    expect($messageCount)->toBeGreaterThan(0);
    
    $channel->close();
    $connection->close();
});

test('it can pop jobs from queue', function () {
    // Push a job first
    $job = new class
    {
        public function handle()
        {
            return 'test';
        }
    };
    
    $this->driver->push($job, 'test-data', 'fiberflow_test');
    
    // Pop the job
    $poppedJob = $this->driver->pop('fiberflow_test');
    
    expect($poppedJob)->not->toBeNull();
});

test('it can push delayed jobs', function () {
    $job = new class
    {
        public function handle()
        {
            return 'delayed';
        }
    };
    
    $this->driver->later(5, $job, 'delayed-data', 'fiberflow_test');
    
    // Job should not be immediately available
    $poppedJob = $this->driver->pop('fiberflow_test');
    expect($poppedJob)->toBeNull();
});

test('it can handle multiple jobs', function () {
    $jobs = [];
    for ($i = 1; $i <= 10; $i++) {
        $jobs[] = new class
        {
            public function handle()
            {
                return 'test';
            }
        };
    }
    
    foreach ($jobs as $job) {
        $this->driver->push($job, 'test-data', 'fiberflow_test');
    }
    
    // Verify all jobs were pushed
    $connection = new AMQPStreamConnection(
        '127.0.0.1',
        5673,
        'fiberflow',
        'fiberflow',
        '/'
    );
    $channel = $connection->channel();
    
    list($queue, $messageCount, $consumerCount) = $channel->queue_declare('fiberflow_test', true);
    
    expect($messageCount)->toBe(10);
    
    $channel->close();
    $connection->close();
});

test('it can get queue size', function () {
    // Push some jobs
    for ($i = 1; $i <= 5; $i++) {
        $job = new class
        {
            public function handle()
            {
                return 'test';
            }
        };
        $this->driver->push($job, 'test-data', 'fiberflow_test');
    }
    
    $size = $this->driver->size('fiberflow_test');
    expect($size)->toBe(5);
});


<?php

declare(strict_types=1);

use FiberFlow\Database\AsyncDbConnection;
use FiberFlow\Queue\Drivers\DatabaseQueueDriver;

it('initializes with connection', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    expect($driver)->toBeInstanceOf(DatabaseQueueDriver::class);
});

it('pushes job to queue', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $connection->shouldReceive('insert')
        ->once()
        ->with('jobs', Mockery::on(function ($data) {
            return $data['queue'] === 'default'
                && $data['payload'] === '{"job":"test"}'
                && $data['attempts'] === 0
                && isset($data['available_at'])
                && isset($data['created_at']);
        }))
        ->andReturn(123);

    $jobId = $driver->push('default', '{"job":"test"}', 0);

    expect($jobId)->toBe('123');
});

it('pushes delayed job to queue', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $delay = 60;
    $expectedAvailableAt = time() + $delay;

    $connection->shouldReceive('insert')
        ->once()
        ->with('jobs', Mockery::on(function ($data) use ($expectedAvailableAt) {
            return $data['queue'] === 'default'
                && abs($data['available_at'] - $expectedAvailableAt) <= 1;
        }))
        ->andReturn(456);

    $jobId = $driver->push('default', '{"job":"delayed"}', $delay);

    expect($jobId)->toBe('456');
});

it('returns null when no job available', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $connection->shouldReceive('fetchOne')
        ->once()
        ->andReturn(null);

    $job = $driver->pop('default');

    expect($job)->toBeNull();
});

it('returns null when queue is empty', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $connection->shouldReceive('fetchOne')
        ->once()
        ->andReturn(null);

    $job = $driver->pop('default');

    expect($job)->toBeNull();
});

it('deletes job from queue', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $connection->shouldReceive('delete')
        ->once()
        ->with('jobs', ['id' => 123]);

    $driver->delete('default', '123');

    expect(true)->toBeTrue();
});

it('releases job back to queue', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $delay = 30;

    $connection->shouldReceive('update')
        ->once()
        ->with('jobs', Mockery::type('array'), ['id' => 123])
        ->andReturn(1);

    $driver->release('default', '123', $delay);

    expect(true)->toBeTrue();
});

it('gets queue size', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $connection->shouldReceive('fetchOne')
        ->once()
        ->with(Mockery::on(function ($query) {
            return str_contains($query, 'COUNT(*)');
        }), ['default'])
        ->andReturn(['count' => 42]);

    $size = $driver->size('default');

    expect($size)->toBe(42);
});

it('clears queue', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $connection->shouldReceive('delete')
        ->once()
        ->with('jobs', ['queue' => 'default']);

    $driver->clear('default');

    expect(true)->toBeTrue();
});

it('returns driver name', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    expect($driver->getName())->toBe('database');
});

it('indicates it is async', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    expect($driver->isAsync())->toBeTrue();
});

it('can close connection', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $connection->shouldReceive('close')
        ->once();

    $driver->close();

    expect(true)->toBeTrue();
});

it('pushes job with custom queue name', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $connection->shouldReceive('insert')
        ->once()
        ->with('jobs', Mockery::on(function ($data) {
            return $data['queue'] === 'custom-queue';
        }))
        ->andReturn(789);

    $jobId = $driver->push('custom-queue', '{"job":"custom"}', 0);

    expect($jobId)->toBe('789');
});

it('releases job with delay', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $delay = 120;

    $connection->shouldReceive('update')
        ->once()
        ->with('jobs', Mockery::on(function ($data) use ($delay) {
            $expectedAvailableAt = time() + $delay;

            return isset($data['available_at'])
                && abs($data['available_at'] - $expectedAvailableAt) <= 1
                && $data['reserved_at'] === null;
        }), ['id' => 456])
        ->andReturn(1);

    $driver->release('default', '456', $delay);

    expect(true)->toBeTrue();
});

it('releases job without delay', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $connection->shouldReceive('update')
        ->once()
        ->with('jobs', Mockery::on(function ($data) {
            return $data['reserved_at'] === null
                && isset($data['available_at']);
        }), ['id' => 789])
        ->andReturn(1);

    $driver->release('default', '789', 0);

    expect(true)->toBeTrue();
});

it('gets size of empty queue', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $connection->shouldReceive('fetchOne')
        ->once()
        ->andReturn(['count' => 0]);

    $size = $driver->size('default');

    expect($size)->toBe(0);
});

it('gets size of queue with multiple jobs', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $connection->shouldReceive('fetchOne')
        ->once()
        ->andReturn(['count' => 150]);

    $size = $driver->size('default');

    expect($size)->toBe(150);
});

it('can initialize with custom default queue', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'custom-default');

    expect($driver)->toBeInstanceOf(DatabaseQueueDriver::class);
});

it('handles multiple push operations', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $connection->shouldReceive('insert')
        ->times(3)
        ->andReturn(1, 2, 3);

    $id1 = $driver->push('default', '{"job":"1"}', 0);
    $id2 = $driver->push('default', '{"job":"2"}', 0);
    $id3 = $driver->push('default', '{"job":"3"}', 0);

    expect($id1)->toBe('1');
    expect($id2)->toBe('2');
    expect($id3)->toBe('3');
});

it('handles multiple delete operations', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $connection->shouldReceive('delete')
        ->times(3)
        ->with('jobs', Mockery::type('array'));

    $driver->delete('default', '1');
    $driver->delete('default', '2');
    $driver->delete('default', '3');

    expect(true)->toBeTrue();
});

it('can set custom table name', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $result = $driver->setTable('custom_jobs');

    expect($result)->toBe($driver);
});

it('uses custom table name for operations', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');
    $driver->setTable('custom_jobs');

    $connection->shouldReceive('insert')
        ->once()
        ->with('custom_jobs', Mockery::type('array'))
        ->andReturn(1);

    $driver->push('default', '{"job":"test"}', 0);
});

it('pops job and updates reservation', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $jobData = [
        'id' => 1,
        'queue' => 'default',
        'payload' => '{"job":"test"}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => time(),
        'created_at' => time(),
    ];

    $connection->shouldReceive('fetchOne')
        ->once()
        ->andReturn($jobData);

    $connection->shouldReceive('update')
        ->once()
        ->with('jobs', Mockery::on(function ($data) {
            return isset($data['reserved_at']) && isset($data['attempts']);
        }), ['id' => 1]);

    // Note: We can't test the actual Job creation without full Laravel setup
    // This test verifies the database operations are correct
    try {
        $driver->pop('default');
    } catch (\Throwable $e) {
        // Expected to fail when creating DatabaseJob without proper Laravel setup
        // But the database operations should have been called
    }
});

it('calculates available_at correctly for delayed jobs', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $delay = 60; // 60 seconds
    $beforeTime = time();

    $connection->shouldReceive('insert')
        ->once()
        ->with('jobs', Mockery::on(function ($data) use ($beforeTime, $delay) {
            $expectedAvailableAt = $beforeTime + $delay;
            // Allow 1 second tolerance for test execution time
            return abs($data['available_at'] - $expectedAvailableAt) <= 1;
        }))
        ->andReturn(1);

    $driver->push('default', '{"job":"test"}', $delay);
});

it('increments attempts when popping job', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $jobData = [
        'id' => 1,
        'queue' => 'default',
        'payload' => '{"job":"test"}',
        'attempts' => 2,
        'reserved_at' => null,
        'available_at' => time(),
        'created_at' => time(),
    ];

    $connection->shouldReceive('fetchOne')
        ->once()
        ->andReturn($jobData);

    $connection->shouldReceive('update')
        ->once()
        ->with('jobs', Mockery::on(function ($data) {
            return $data['attempts'] === 3; // Should increment from 2 to 3
        }), ['id' => 1]);

    try {
        $driver->pop('default');
    } catch (\Throwable $e) {
        // Expected to fail when creating DatabaseJob without proper Laravel setup
    }
});

it('clears all jobs from specific queue only', function () {
    $connection = Mockery::mock(AsyncDbConnection::class);
    $driver = new DatabaseQueueDriver($connection, 'default');

    $connection->shouldReceive('delete')
        ->once()
        ->with('jobs', ['queue' => 'emails']);

    $driver->clear('emails');
});

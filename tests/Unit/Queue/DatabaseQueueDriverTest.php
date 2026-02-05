<?php

declare(strict_types=1);

use FiberFlow\Database\AsyncDbConnection;
use FiberFlow\Queue\Drivers\DatabaseQueueDriver;
use Mockery;

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

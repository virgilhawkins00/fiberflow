<?php

declare(strict_types=1);

use FiberFlow\Database\AsyncDbConnection;

beforeEach(function () {
    $this->connection = Mockery::mock(AsyncDbConnection::class)->makePartial();
});

afterEach(function () {
    Mockery::close();
});

test('it begins transaction', function () {
    $this->connection->shouldReceive('query')
        ->once()
        ->with('START TRANSACTION');

    $this->connection->beginTransaction();
});

test('it commits transaction', function () {
    $this->connection->shouldReceive('query')
        ->once()
        ->with('COMMIT');

    $this->connection->commit();
});

test('it rolls back transaction', function () {
    $this->connection->shouldReceive('query')
        ->once()
        ->with('ROLLBACK');

    $this->connection->rollback();
});

test('it executes callback in transaction and commits', function () {
    $this->connection->shouldReceive('query')
        ->once()
        ->with('START TRANSACTION');

    $this->connection->shouldReceive('query')
        ->once()
        ->with('COMMIT');

    $result = $this->connection->transaction(function ($conn) {
        return 'success';
    });

    expect($result)->toBe('success');
});

test('it rolls back transaction on exception', function () {
    $this->connection->shouldReceive('query')
        ->once()
        ->with('START TRANSACTION');

    $this->connection->shouldReceive('query')
        ->once()
        ->with('ROLLBACK');

    $this->connection->shouldReceive('query')
        ->never()
        ->with('COMMIT');

    try {
        $this->connection->transaction(function ($conn) {
            throw new \RuntimeException('Test error');
        });
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toBe('Test error');
    }
});

<?php

declare(strict_types=1);

use FiberFlow\Database\AsyncDbClient;

test('it can create async db client with database disabled', function () {
    config(['fiberflow.database.enabled' => false]);

    $client = new AsyncDbClient;

    expect($client)->toBeInstanceOf(AsyncDbClient::class);
});

test('it throws exception when trying to query with database disabled', function () {
    config(['fiberflow.database.enabled' => false]);

    $client = new AsyncDbClient;

    expect(fn () => $client->select('SELECT 1'))
        ->toThrow(\RuntimeException::class, 'AsyncDb is not enabled');
});

test('it throws exception when trying to insert with database disabled', function () {
    config(['fiberflow.database.enabled' => false]);

    $client = new AsyncDbClient;

    expect(fn () => $client->insert('INSERT INTO test VALUES (1)'))
        ->toThrow(\RuntimeException::class, 'AsyncDb is not enabled');
});

test('it throws exception when trying to update with database disabled', function () {
    config(['fiberflow.database.enabled' => false]);

    $client = new AsyncDbClient;

    expect(fn () => $client->update('UPDATE test SET value = 1'))
        ->toThrow(\RuntimeException::class, 'AsyncDb is not enabled');
});

test('it throws exception when trying to delete with database disabled', function () {
    config(['fiberflow.database.enabled' => false]);

    $client = new AsyncDbClient;

    expect(fn () => $client->delete('DELETE FROM test'))
        ->toThrow(\RuntimeException::class, 'AsyncDb is not enabled');
});

test('it can create client with custom pool size', function () {
    config(['fiberflow.database.enabled' => false]);

    $client = new AsyncDbClient(poolSize: 20);

    expect($client)->toBeInstanceOf(AsyncDbClient::class);
});

test('it can create client with custom timeout', function () {
    config(['fiberflow.database.enabled' => false]);

    $client = new AsyncDbClient(timeout: 10);

    expect($client)->toBeInstanceOf(AsyncDbClient::class);
});

test('it can create client with both custom pool size and timeout', function () {
    config(['fiberflow.database.enabled' => false]);

    $client = new AsyncDbClient(poolSize: 15, timeout: 8);

    expect($client)->toBeInstanceOf(AsyncDbClient::class);
});


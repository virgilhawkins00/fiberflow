<?php

declare(strict_types=1);

use FiberFlow\Database\AsyncDbClient;

it('initializes with default configuration', function () {
    config(['fiberflow.database.enabled' => false]);

    $client = new AsyncDbClient;

    expect($client)->toBeInstanceOf(AsyncDbClient::class);
});

it('throws exception when disabled and trying to select', function () {
    config(['fiberflow.database.enabled' => false]);

    $client = new AsyncDbClient;

    expect(fn () => $client->select('SELECT * FROM users'))
        ->toThrow(RuntimeException::class, 'AsyncDb is not enabled');
});

it('throws exception when disabled and trying to insert', function () {
    config(['fiberflow.database.enabled' => false]);

    $client = new AsyncDbClient;

    expect(fn () => $client->insert('INSERT INTO users (name) VALUES (?)', ['John']))
        ->toThrow(RuntimeException::class, 'AsyncDb is not enabled');
});

it('throws exception when disabled and trying to update', function () {
    config(['fiberflow.database.enabled' => false]);

    $client = new AsyncDbClient;

    expect(fn () => $client->update('UPDATE users SET name = ? WHERE id = ?', ['Jane', 1]))
        ->toThrow(RuntimeException::class, 'AsyncDb is not enabled');
});

it('throws exception when disabled and trying to delete', function () {
    config(['fiberflow.database.enabled' => false]);

    $client = new AsyncDbClient;

    expect(fn () => $client->delete('DELETE FROM users WHERE id = ?', [1]))
        ->toThrow(RuntimeException::class, 'AsyncDb is not enabled');
});

it('throws exception when disabled and trying to query', function () {
    config(['fiberflow.database.enabled' => false]);

    $client = new AsyncDbClient;

    expect(fn () => $client->query('SELECT * FROM users'))
        ->toThrow(RuntimeException::class, 'AsyncDb is not enabled');
});

it('can create client with custom pool size', function () {
    config(['fiberflow.database.enabled' => false]);

    $client = new AsyncDbClient(poolSize: 20);

    expect($client)->toBeInstanceOf(AsyncDbClient::class);
});

it('can create client with custom timeout', function () {
    config(['fiberflow.database.enabled' => false]);

    $client = new AsyncDbClient(timeout: 10);

    expect($client)->toBeInstanceOf(AsyncDbClient::class);
});

it('has initializePool method', function () {
    $reflection = new ReflectionClass(AsyncDbClient::class);
    expect($reflection->hasMethod('initializePool'))->toBeTrue();
});

it('has select method', function () {
    $reflection = new ReflectionClass(AsyncDbClient::class);
    expect($reflection->hasMethod('select'))->toBeTrue();
});

it('has insert method', function () {
    $reflection = new ReflectionClass(AsyncDbClient::class);
    expect($reflection->hasMethod('insert'))->toBeTrue();
});

it('has update method', function () {
    $reflection = new ReflectionClass(AsyncDbClient::class);
    expect($reflection->hasMethod('update'))->toBeTrue();
});

it('has delete method', function () {
    $reflection = new ReflectionClass(AsyncDbClient::class);
    expect($reflection->hasMethod('delete'))->toBeTrue();
});

it('has query method', function () {
    $reflection = new ReflectionClass(AsyncDbClient::class);
    expect($reflection->hasMethod('query'))->toBeTrue();
});

it('has enabled property', function () {
    config(['fiberflow.database.enabled' => false]);
    $client = new AsyncDbClient;

    $reflection = new ReflectionClass($client);
    $property = $reflection->getProperty('enabled');
    $property->setAccessible(true);

    expect($property->getValue($client))->toBeFalse();
});

it('has pool property', function () {
    config(['fiberflow.database.enabled' => false]);
    $client = new AsyncDbClient;

    $reflection = new ReflectionClass($client);
    $property = $reflection->getProperty('pool');
    $property->setAccessible(true);

    expect($property->getValue($client))->toBeNull();
});

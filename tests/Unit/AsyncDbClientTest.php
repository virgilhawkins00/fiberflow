<?php

declare(strict_types=1);

use FiberFlow\Database\AsyncDbClient;

it('initializes with default configuration', function () {
    config(['fiberflow.database.enabled' => false]);
    
    $client = new AsyncDbClient();
    
    expect($client)->toBeInstanceOf(AsyncDbClient::class);
});

it('throws exception when disabled and trying to select', function () {
    config(['fiberflow.database.enabled' => false]);
    
    $client = new AsyncDbClient();
    
    expect(fn () => $client->select('SELECT * FROM users'))
        ->toThrow(RuntimeException::class, 'AsyncDb is not enabled');
});

it('throws exception when disabled and trying to insert', function () {
    config(['fiberflow.database.enabled' => false]);
    
    $client = new AsyncDbClient();
    
    expect(fn () => $client->insert('INSERT INTO users (name) VALUES (?)', ['John']))
        ->toThrow(RuntimeException::class, 'AsyncDb is not enabled');
});

it('throws exception when disabled and trying to update', function () {
    config(['fiberflow.database.enabled' => false]);
    
    $client = new AsyncDbClient();
    
    expect(fn () => $client->update('UPDATE users SET name = ? WHERE id = ?', ['Jane', 1]))
        ->toThrow(RuntimeException::class, 'AsyncDb is not enabled');
});

it('throws exception when disabled and trying to delete', function () {
    config(['fiberflow.database.enabled' => false]);
    
    $client = new AsyncDbClient();
    
    expect(fn () => $client->delete('DELETE FROM users WHERE id = ?', [1]))
        ->toThrow(RuntimeException::class, 'AsyncDb is not enabled');
});

it('throws exception when disabled and trying to query', function () {
    config(['fiberflow.database.enabled' => false]);
    
    $client = new AsyncDbClient();
    
    expect(fn () => $client->query('SELECT * FROM users'))
        ->toThrow(RuntimeException::class, 'AsyncDb is not enabled');
});




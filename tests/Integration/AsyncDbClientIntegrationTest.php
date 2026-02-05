<?php

declare(strict_types=1);

use FiberFlow\Database\AsyncDbClient;

uses()->group('integration', 'database');

beforeEach(function () {
    // Configure database connection for integration tests
    config()->set('fiberflow.database.enabled', true);
    config()->set('database.connections.mysql.host', '127.0.0.1');
    config()->set('database.connections.mysql.port', 3307);
    config()->set('database.connections.mysql.database', 'fiberflow_test');
    config()->set('database.connections.mysql.username', 'fiberflow');
    config()->set('database.connections.mysql.password', 'fiberflow');

    $this->client = new AsyncDbClient(poolSize: 5);
});

afterEach(function () {
    // Clean up test tables
    try {
        $this->client->execute('DROP TABLE IF EXISTS test_users');
        $this->client->execute('DROP TABLE IF EXISTS test_posts');
    } catch (\Throwable $e) {
        // Ignore cleanup errors
    }
});

test('it can connect to MySQL database', function () {
    $result = $this->client->select('SELECT 1 as num');

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0]['num'])->toBe(1);
});

test('it can create tables and insert data', function () {
    // Create table
    $this->client->execute('
        CREATE TABLE test_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');
    
    // Insert data
    $this->client->insert('INSERT INTO test_users (name, email) VALUES (?, ?)', [
        'John Doe',
        'john@example.com',
    ]);
    
    // Select data
    $users = $this->client->select('SELECT * FROM test_users');
    
    expect($users)->toHaveCount(1);
    expect($users[0]['name'])->toBe('John Doe');
    expect($users[0]['email'])->toBe('john@example.com');
});

test('it can update records', function () {
    // Create and insert
    $this->client->execute('
        CREATE TABLE test_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL
        )
    ');
    
    $this->client->insert('INSERT INTO test_users (name, email) VALUES (?, ?)', [
        'John Doe',
        'john@example.com',
    ]);
    
    // Update
    $this->client->update('UPDATE test_users SET name = ? WHERE email = ?', [
        'Jane Doe',
        'john@example.com',
    ]);
    
    // Verify
    $users = $this->client->select('SELECT * FROM test_users');
    expect($users[0]['name'])->toBe('Jane Doe');
});

test('it can delete records', function () {
    // Create and insert
    $this->client->execute('
        CREATE TABLE test_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL
        )
    ');
    
    $this->client->insert('INSERT INTO test_users (name) VALUES (?)', ['User 1']);
    $this->client->insert('INSERT INTO test_users (name) VALUES (?)', ['User 2']);
    
    // Delete
    $this->client->delete('DELETE FROM test_users WHERE name = ?', ['User 1']);
    
    // Verify
    $users = $this->client->select('SELECT * FROM test_users');
    expect($users)->toHaveCount(1);
    expect($users[0]['name'])->toBe('User 2');
});

test('it can handle concurrent queries', function () {
    // Create table
    $this->client->execute('
        CREATE TABLE test_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL
        )
    ');
    
    // Insert multiple records concurrently using fibers
    $fibers = [];
    for ($i = 1; $i <= 10; $i++) {
        $fibers[] = new Fiber(function () use ($i) {
            $this->client->insert('INSERT INTO test_users (name) VALUES (?)', ["User $i"]);
        });
    }
    
    foreach ($fibers as $fiber) {
        $fiber->start();
    }
    
    // Verify all records were inserted
    $users = $this->client->select('SELECT * FROM test_users ORDER BY id');
    expect($users)->toHaveCount(10);
});

test('it can use connection pool efficiently', function () {
    // Create table
    $this->client->execute('
        CREATE TABLE test_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL
        )
    ');
    
    // Make more queries than pool size (pool size is 5)
    for ($i = 1; $i <= 20; $i++) {
        $this->client->insert('INSERT INTO test_posts (title) VALUES (?)', ["Post $i"]);
    }
    
    $posts = $this->client->select('SELECT COUNT(*) as count FROM test_posts');
    expect($posts[0]['count'])->toBe(20);
});

test('it handles query errors gracefully', function () {
    try {
        // Invalid SQL
        $this->client->select('SELECT * FROM non_existent_table');
        expect(false)->toBeTrue(); // Should not reach here
    } catch (\Throwable $e) {
        expect($e->getMessage())->toContain('non_existent_table');
    }
});

test('it can execute transactions', function () {
    // Create table
    $this->client->execute('
        CREATE TABLE test_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL
        )
    ');
    
    // Start transaction
    $this->client->execute('START TRANSACTION');
    
    try {
        $this->client->insert('INSERT INTO test_users (name) VALUES (?)', ['User 1']);
        $this->client->insert('INSERT INTO test_users (name) VALUES (?)', ['User 2']);
        
        // Commit
        $this->client->execute('COMMIT');
    } catch (\Throwable $e) {
        $this->client->execute('ROLLBACK');
        throw $e;
    }
    
    $users = $this->client->select('SELECT * FROM test_users');
    expect($users)->toHaveCount(2);
});


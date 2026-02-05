<?php

declare(strict_types=1);

namespace Tests\Integration;

use FiberFlow\Database\AsyncDbClient;

uses(IntegrationTestCase::class)->group('integration', 'database');

beforeEach(function () {
    // Skip if MySQL is not available
    if (! $this->isMySqlAvailable()) {
        $this->markTestSkipped('MySQL not available for integration tests');
    }

    $this->client = new AsyncDbClient(poolSize: 5);

    // Clean test tables before each test (tables created by setup-test-schema.sql)
    try {
        $this->client->query('TRUNCATE TABLE fiberflow_test_users');
        $this->client->query('TRUNCATE TABLE fiberflow_test_posts');
        $this->client->query('TRUNCATE TABLE fiberflow_test_jobs');
    } catch (\Throwable $e) {
        // Tables might not exist - ignore
    }
});

test('it can connect to MySQL database', function () {
    $result = $this->client->select('SELECT 1 as num');

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0]['num'])->toBe(1);
});

test('it can insert records', function () {
    // Test insert() method (lines 79-87) - exercises INSERT operation
    $insertedId = $this->client->insert(
        'INSERT INTO fiberflow_test_users (name, email) VALUES (?, ?)',
        ['John Doe', 'john@example.com'],
    );

    expect($insertedId)->toBeGreaterThan(0);

    // Verify insertion
    $users = $this->client->select('SELECT * FROM fiberflow_test_users WHERE id = ?', [$insertedId]);
    expect($users)->toHaveCount(1);
    expect($users[0]['name'])->toBe('John Doe');
    expect($users[0]['email'])->toBe('john@example.com');
});

test('it can update records', function () {
    // Insert a record first
    $id = $this->client->insert(
        'INSERT INTO fiberflow_test_users (name, email) VALUES (?, ?)',
        ['John Doe', 'john@example.com'],
    );

    // Test update() method (lines 95-103) - exercises UPDATE operation
    $affectedRows = $this->client->update(
        'UPDATE fiberflow_test_users SET name = ? WHERE id = ?',
        ['Jane Doe', $id],
    );

    expect($affectedRows)->toBe(1);

    // Verify update
    $users = $this->client->select('SELECT * FROM fiberflow_test_users WHERE id = ?', [$id]);
    expect($users[0]['name'])->toBe('Jane Doe');
});

test('it can delete records', function () {
    // Clean table first to ensure clean state
    $this->client->query('DELETE FROM fiberflow_test_users');

    // Insert records
    $id1 = $this->client->insert(
        'INSERT INTO fiberflow_test_users (name, email) VALUES (?, ?)',
        ['User 1', 'user1@example.com'],
    );
    $id2 = $this->client->insert(
        'INSERT INTO fiberflow_test_users (name, email) VALUES (?, ?)',
        ['User 2', 'user2@example.com'],
    );

    // Test delete() method (lines 113-126) - exercises DELETE operation
    $affectedRows = $this->client->delete(
        'DELETE FROM fiberflow_test_users WHERE id = ?',
        [$id1],
    );

    expect($affectedRows)->toBe(1);

    // Verify deletion
    $users = $this->client->select('SELECT * FROM fiberflow_test_users');
    expect($users)->toHaveCount(1);
    expect($users[0]['id'])->toBe($id2);
});

test('it can execute select queries with bindings', function () {
    // Test select with bindings - exercises select() method (lines 65-71)
    $result = $this->client->select(
        'SELECT ? as value, ? as name',
        [42, 'test'],
    );

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0]['value'])->toBe(42);
    expect($result[0]['name'])->toBe('test');
});

test('it can execute query method', function () {
    // Test query() method (lines 129-141) - exercises generic query execution
    $result = $this->client->query('SELECT DATABASE() as db_name');

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0])->toHaveKey('db_name');
    expect($result[0]['db_name'])->toBe('fiberflow_test');
});

test('it can handle concurrent queries', function () {
    // Test concurrent queries to exercise connection pool
    $results = [];

    for ($i = 1; $i <= 10; $i++) {
        $results[] = $this->client->select('SELECT ? as num', [$i]);
    }

    expect($results)->toHaveCount(10);
    foreach ($results as $index => $result) {
        expect($result[0]['num'])->toBe($index + 1);
    }
});

test('it can use connection pool efficiently', function () {
    // Make more queries than pool size (pool size is 5) to test pool reuse
    $results = [];

    for ($i = 1; $i <= 20; $i++) {
        $results[] = $this->client->select('SELECT ? as value', [$i]);
    }

    expect($results)->toHaveCount(20);
    foreach ($results as $index => $result) {
        expect($result[0]['value'])->toBe($index + 1);
    }
});

test('it handles query errors gracefully', function () {
    try {
        // Invalid SQL - should throw exception
        $this->client->select('SELECT * FROM non_existent_table_xyz');
        expect(false)->toBeTrue(); // Should not reach here
    } catch (\Throwable $e) {
        expect($e->getMessage())->toContain('non_existent_table');
    }
});

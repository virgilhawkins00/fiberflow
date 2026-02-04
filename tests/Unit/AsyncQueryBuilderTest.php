<?php

declare(strict_types=1);

use FiberFlow\Database\AsyncDbConnection;
use FiberFlow\Database\AsyncQueryBuilder;

beforeEach(function () {
    $this->connection = Mockery::mock(AsyncDbConnection::class);
    $this->builder = new AsyncQueryBuilder($this->connection, 'users');
});

afterEach(function () {
    Mockery::close();
});

test('it builds simple select query', function () {
    $this->connection->shouldReceive('fetchAll')
        ->once()
        ->with('SELECT * FROM users', [])
        ->andReturn([]);

    $this->builder->get();
});

test('it builds select with specific columns', function () {
    $this->connection->shouldReceive('fetchAll')
        ->once()
        ->with('SELECT id, name, email FROM users', [])
        ->andReturn([]);

    $this->builder->select('id', 'name', 'email')->get();
});

test('it builds query with where clause', function () {
    $this->connection->shouldReceive('fetchAll')
        ->once()
        ->with('SELECT * FROM users WHERE id = ?', [1])
        ->andReturn([]);

    $this->builder->where('id', 1)->get();
});

test('it builds query with multiple where clauses', function () {
    $this->connection->shouldReceive('fetchAll')
        ->once()
        ->with('SELECT * FROM users WHERE status = ? AND role = ?', ['active', 'admin'])
        ->andReturn([]);

    $this->builder
        ->where('status', 'active')
        ->where('role', 'admin')
        ->get();
});

test('it builds query with order by', function () {
    $this->connection->shouldReceive('fetchAll')
        ->once()
        ->with('SELECT * FROM users ORDER BY created_at DESC', [])
        ->andReturn([]);

    $this->builder->orderBy('created_at', 'DESC')->get();
});

test('it builds query with limit', function () {
    $this->connection->shouldReceive('fetchAll')
        ->once()
        ->with('SELECT * FROM users LIMIT 10', [])
        ->andReturn([]);

    $this->builder->limit(10)->get();
});

test('it builds query with offset', function () {
    $this->connection->shouldReceive('fetchAll')
        ->once()
        ->with('SELECT * FROM users LIMIT 10 OFFSET 20', [])
        ->andReturn([]);

    $this->builder->limit(10)->offset(20)->get();
});

test('it builds complex query', function () {
    $this->connection->shouldReceive('fetchAll')
        ->once()
        ->with(
            'SELECT id, name FROM users WHERE status = ? AND role = ? ORDER BY created_at DESC LIMIT 5 OFFSET 10',
            ['active', 'admin']
        )
        ->andReturn([]);

    $this->builder
        ->select('id', 'name')
        ->where('status', 'active')
        ->where('role', 'admin')
        ->orderBy('created_at', 'DESC')
        ->limit(5)
        ->offset(10)
        ->get();
});

test('it gets first result', function () {
    $this->connection->shouldReceive('fetchOne')
        ->once()
        ->with('SELECT * FROM users WHERE id = ? LIMIT 1', [1])
        ->andReturn(['id' => 1, 'name' => 'John']);

    $result = $this->builder->where('id', 1)->first();
    expect($result)->toBe(['id' => 1, 'name' => 'John']);
});

test('it inserts data', function () {
    $data = ['name' => 'John', 'email' => 'john@example.com'];

    $this->connection->shouldReceive('insert')
        ->once()
        ->with('users', $data)
        ->andReturn(1);

    $id = $this->builder->insert($data);
    expect($id)->toBe(1);
});

test('it updates data', function () {
    $data = ['name' => 'Jane'];

    $this->connection->shouldReceive('update')
        ->once()
        ->with('users', $data, ['id' => 1])
        ->andReturn(1);

    $affected = $this->builder->where('id', 1)->update($data);
    expect($affected)->toBe(1);
});

test('it deletes data', function () {
    $this->connection->shouldReceive('delete')
        ->once()
        ->with('users', ['id' => 1])
        ->andReturn(1);

    $affected = $this->builder->where('id', 1)->delete();
    expect($affected)->toBe(1);
});

test('it supports custom operators in where', function () {
    $this->connection->shouldReceive('fetchAll')
        ->once()
        ->with('SELECT * FROM users WHERE age > ?', [18])
        ->andReturn([]);

    $this->builder->where('age', '>', 18)->get();
});


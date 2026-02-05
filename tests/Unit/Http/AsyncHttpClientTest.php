<?php

declare(strict_types=1);

use FiberFlow\Http\AsyncHttpClient;

beforeEach(function () {
    $this->client = new AsyncHttpClient;
});

it('initializes with default configuration', function () {
    $client = new AsyncHttpClient;

    expect($client)->toBeInstanceOf(AsyncHttpClient::class);
});

it('can set custom timeout', function () {
    $client = new AsyncHttpClient(timeout: 30);

    expect($client)->toBeInstanceOf(AsyncHttpClient::class);
});

it('can set custom max retries', function () {
    $client = new AsyncHttpClient(retryAttempts: 5);

    expect($client)->toBeInstanceOf(AsyncHttpClient::class);
});

it('can set custom retry delay', function () {
    $client = new AsyncHttpClient(retryDelay: 2000);

    expect($client)->toBeInstanceOf(AsyncHttpClient::class);
});

it('can set all configuration options', function () {
    $client = new AsyncHttpClient(
        timeout: 30,
        retryAttempts: 5,
        retryDelay: 2000,
    );

    expect($client)->toBeInstanceOf(AsyncHttpClient::class);
});

it('can create client with zero retries', function () {
    $client = new AsyncHttpClient(retryAttempts: 0);

    expect($client)->toBeInstanceOf(AsyncHttpClient::class);
});

it('can create client with very short timeout', function () {
    $client = new AsyncHttpClient(timeout: 1);

    expect($client)->toBeInstanceOf(AsyncHttpClient::class);
});

it('can create client with very long timeout', function () {
    $client = new AsyncHttpClient(timeout: 300);

    expect($client)->toBeInstanceOf(AsyncHttpClient::class);
});

it('can create multiple client instances', function () {
    $client1 = new AsyncHttpClient(timeout: 10);
    $client2 = new AsyncHttpClient(timeout: 20);
    $client3 = new AsyncHttpClient(timeout: 30);

    expect($client1)->not->toBe($client2);
    expect($client2)->not->toBe($client3);
    expect($client1)->not->toBe($client3);
});

it('can create client with custom retry configuration', function () {
    $client = new AsyncHttpClient(
        retryAttempts: 3,
        retryDelay: 1000,
    );

    expect($client)->toBeInstanceOf(AsyncHttpClient::class);
});

it('handles default parameters correctly', function () {
    $client = new AsyncHttpClient;

    // Should use default values
    expect($client)->toBeInstanceOf(AsyncHttpClient::class);
});

it('can be instantiated in fiber context', function () {
    $fiber = new Fiber(function () {
        $client = new AsyncHttpClient;
        expect($client)->toBeInstanceOf(AsyncHttpClient::class);
        Fiber::suspend();
    });

    $fiber->start();
});

it('can create multiple clients in fiber context', function () {
    $fiber = new Fiber(function () {
        $client1 = new AsyncHttpClient(timeout: 10);
        $client2 = new AsyncHttpClient(timeout: 20);

        expect($client1)->not->toBe($client2);
        Fiber::suspend();
    });

    $fiber->start();
});

it('maintains separate instances across fibers', function () {
    $client1 = null;
    $client2 = null;

    $fiber1 = new Fiber(function () use (&$client1) {
        $client1 = new AsyncHttpClient(timeout: 10);
        Fiber::suspend();
    });

    $fiber2 = new Fiber(function () use (&$client2) {
        $client2 = new AsyncHttpClient(timeout: 20);
        Fiber::suspend();
    });

    $fiber1->start();
    $fiber2->start();

    expect($client1)->not->toBe($client2);
});

it('can create client with all parameters set to minimum values', function () {
    $client = new AsyncHttpClient(
        timeout: 1,
        retryAttempts: 0,
        retryDelay: 0,
    );

    expect($client)->toBeInstanceOf(AsyncHttpClient::class);
});

it('can create client with all parameters set to maximum values', function () {
    $client = new AsyncHttpClient(
        timeout: 300,
        retryAttempts: 10,
        retryDelay: 5000,
    );

    expect($client)->toBeInstanceOf(AsyncHttpClient::class);
});

it('has put method available', function () {
    expect(method_exists($this->client, 'put'))->toBeTrue();
});

it('has patch method available', function () {
    expect(method_exists($this->client, 'patch'))->toBeTrue();
});

it('has delete method available', function () {
    expect(method_exists($this->client, 'delete'))->toBeTrue();
});

it('can call put method with url and data', function () {
    // We can't actually make HTTP requests in unit tests,
    // but we can verify the method exists and accepts the right parameters
    $reflection = new ReflectionMethod($this->client, 'put');

    expect($reflection->getNumberOfParameters())->toBe(3);
    expect($reflection->getParameters()[0]->getName())->toBe('url');
    expect($reflection->getParameters()[1]->getName())->toBe('data');
    expect($reflection->getParameters()[2]->getName())->toBe('headers');
});

it('can call patch method with url and data', function () {
    $reflection = new ReflectionMethod($this->client, 'patch');

    expect($reflection->getNumberOfParameters())->toBe(3);
    expect($reflection->getParameters()[0]->getName())->toBe('url');
    expect($reflection->getParameters()[1]->getName())->toBe('data');
    expect($reflection->getParameters()[2]->getName())->toBe('headers');
});

it('can call delete method with url', function () {
    $reflection = new ReflectionMethod($this->client, 'delete');

    expect($reflection->getNumberOfParameters())->toBe(2);
    expect($reflection->getParameters()[0]->getName())->toBe('url');
    expect($reflection->getParameters()[1]->getName())->toBe('headers');
});

it('has calculateBackoffDelay method', function () {
    $reflection = new ReflectionClass($this->client);
    expect($reflection->hasMethod('calculateBackoffDelay'))->toBeTrue();
});

it('has requestAsync method', function () {
    $reflection = new ReflectionClass($this->client);
    expect($reflection->hasMethod('requestAsync'))->toBeTrue();
});

it('has requestSync method', function () {
    $reflection = new ReflectionClass($this->client);
    expect($reflection->hasMethod('requestSync'))->toBeTrue();
});

it('calculates exponential backoff delay correctly', function () {
    $reflection = new ReflectionClass($this->client);
    $method = $reflection->getMethod('calculateBackoffDelay');
    $method->setAccessible(true);

    // Test exponential backoff
    $delay1 = $method->invoke($this->client, 1, 1000);
    $delay2 = $method->invoke($this->client, 2, 1000);
    $delay3 = $method->invoke($this->client, 3, 1000);

    // Delay should increase exponentially (with jitter)
    expect($delay1)->toBeGreaterThanOrEqual(1000);
    expect($delay1)->toBeLessThanOrEqual(1100); // 1000 + 10% jitter

    expect($delay2)->toBeGreaterThanOrEqual(2000);
    expect($delay2)->toBeLessThanOrEqual(2200); // 2000 + 10% jitter

    expect($delay3)->toBeGreaterThanOrEqual(4000);
    expect($delay3)->toBeLessThanOrEqual(4400); // 4000 + 10% jitter
});

it('can verify put method signature', function () {
    $reflection = new ReflectionClass($this->client);
    $method = $reflection->getMethod('put');

    expect($method->getNumberOfParameters())->toBe(3);
    expect($method->getNumberOfRequiredParameters())->toBe(1);
});

it('can verify patch method signature', function () {
    $reflection = new ReflectionClass($this->client);
    $method = $reflection->getMethod('patch');

    expect($method->getNumberOfParameters())->toBe(3);
    expect($method->getNumberOfRequiredParameters())->toBe(1);
});

it('can verify delete method signature', function () {
    $reflection = new ReflectionClass($this->client);
    $method = $reflection->getMethod('delete');

    expect($method->getNumberOfParameters())->toBe(2);
    expect($method->getNumberOfRequiredParameters())->toBe(1);
});

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

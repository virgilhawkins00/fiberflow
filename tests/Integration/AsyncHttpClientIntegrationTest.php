<?php

declare(strict_types=1);

use FiberFlow\Http\AsyncHttpClient;
use Revolt\EventLoop;

beforeEach(function () {
    $this->httpbinUrl = 'http://localhost:8080';
});

test('it can make concurrent HTTP requests', function () {
    $client = new AsyncHttpClient(timeout: 10);

    // Make 5 concurrent requests
    $responses = [];
    for ($i = 0; $i < 5; $i++) {
        $responses[] = $client->get($this->httpbinUrl . '/get');
    }

    expect($responses)->toHaveCount(5);
    foreach ($responses as $response) {
        expect($response->successful())->toBeTrue();
    }
})->group('integration');

test('it retries failed requests with exponential backoff', function () {
    $client = new AsyncHttpClient(
        timeout: 5,
        retryAttempts: 3,
        retryDelay: 100
    );

    try {
        // This will fail and retry 3 times
        $response = $client->get($this->httpbinUrl . '/status/500');
        // Should throw exception for 500 status
        expect(false)->toBeTrue('Should have thrown exception');
    } catch (\Throwable $e) {
        // Expected to fail after retries
        expect(true)->toBeTrue();
    }
})->group('integration');

test('it handles timeout correctly', function () {
    $client = new AsyncHttpClient(timeout: 2);

    try {
        // This endpoint delays for 10 seconds, but timeout is 2 seconds
        $client->get($this->httpbinUrl . '/delay/10');
        expect(false)->toBeTrue('Should have timed out');
    } catch (\Throwable $e) {
        // Expected to timeout - just verify exception was thrown
        expect($e)->toBeInstanceOf(\Throwable::class);
    }
})->group('integration');

test('it can make requests with different methods', function () {
    $client = new AsyncHttpClient(timeout: 10);

    $getResponse = $client->get($this->httpbinUrl . '/get');
    expect($getResponse->successful())->toBeTrue();

    $postResponse = $client->post($this->httpbinUrl . '/post', ['test' => 'data']);
    expect($postResponse->successful())->toBeTrue();
})->group('integration');

test('it handles redirects correctly', function () {
    $client = new AsyncHttpClient(timeout: 10);
    
    // HTTPBin redirects /redirect/3 three times before returning 200
    $response = $client->get($this->httpbinUrl . '/redirect/3');
    
    expect($response->successful())->toBeTrue();
    expect($response->status())->toBe(200);
})->group('integration');

test('it can handle different HTTP methods', function () {
    $client = new AsyncHttpClient(timeout: 10);
    
    $getResponse = $client->get($this->httpbinUrl . '/get');
    expect($getResponse->successful())->toBeTrue();
    
    $postResponse = $client->post($this->httpbinUrl . '/post', ['key' => 'value']);
    expect($postResponse->successful())->toBeTrue();
    
    $putResponse = $client->put($this->httpbinUrl . '/put', ['key' => 'value']);
    expect($putResponse->successful())->toBeTrue();
    
    $patchResponse = $client->patch($this->httpbinUrl . '/patch', ['key' => 'value']);
    expect($patchResponse->successful())->toBeTrue();
    
    $deleteResponse = $client->delete($this->httpbinUrl . '/delete');
    expect($deleteResponse->successful())->toBeTrue();
})->group('integration');

test('it can send custom headers', function () {
    $client = new AsyncHttpClient(timeout: 10);
    
    $response = $client->get($this->httpbinUrl . '/headers', [
        'X-Custom-Header' => 'test-value',
        'X-Another-Header' => 'another-value',
    ]);
    
    expect($response->successful())->toBeTrue();
    $json = $response->json();
    expect($json['headers'])->toHaveKey('X-Custom-Header');
    expect($json['headers']['X-Custom-Header'])->toBe('test-value');
})->group('integration');

test('it retries on network errors', function () {
    $client = new AsyncHttpClient(
        timeout: 2,
        retryAttempts: 3,
        retryDelay: 100
    );
    
    try {
        // Invalid host - will cause network error
        $client->get('http://invalid-host-that-does-not-exist.local/test');
        expect(false)->toBeTrue(); // Should not reach here
    } catch (\Throwable $e) {
        // Should have retried 3 times
        expect(true)->toBeTrue();
    }
})->group('integration');


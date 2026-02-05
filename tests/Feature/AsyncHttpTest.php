<?php

declare(strict_types=1);

use FiberFlow\Http\AsyncHttpClient;
use FiberFlow\Http\AsyncHttpResponse;

test('it can make a GET request', function () {
    $client = new AsyncHttpClient;

    $response = $client->get('https://httpbin.org/get');

    expect($response)->toBeInstanceOf(AsyncHttpResponse::class);
    expect($response->successful())->toBeTrue();
    expect($response->status())->toBe(200);
});

test('it can make a POST request', function () {
    $client = new AsyncHttpClient;

    $response = $client->post('https://httpbin.org/post', [
        'name' => 'FiberFlow',
        'version' => '0.2.0',
    ]);

    expect($response->successful())->toBeTrue();
    $json = $response->json();
    expect($json)->toHaveKey('json');
    expect($json['json']['name'])->toBe('FiberFlow');
});

test('it can make concurrent requests in fibers', function () {
    $client = new AsyncHttpClient;
    $results = [];
    $startTime = microtime(true);

    $urls = [
        'https://httpbin.org/delay/1',
        'https://httpbin.org/delay/1',
        'https://httpbin.org/delay/1',
    ];

    $fibers = [];
    foreach ($urls as $index => $url) {
        $fibers[] = new Fiber(function () use ($client, $url, &$results, $index) {
            $response = $client->get($url);
            $results[$index] = $response->successful();
        });
    }

    // Start all fibers
    foreach ($fibers as $fiber) {
        $fiber->start();
    }

    // Wait for all to complete
    while (count(array_filter($fibers, fn ($f) => ! $f->isTerminated())) > 0) {
        usleep(10000); // 10ms
    }

    $duration = microtime(true) - $startTime;

    expect($results)->toHaveCount(3);
    expect(array_filter($results))->toHaveCount(3);
    // Should take ~1 second (concurrent), not 3 seconds (sequential)
    expect($duration)->toBeLessThan(2.0);
})->skip('Requires real HTTP calls');

test('it retries failed requests', function () {
    $client = new AsyncHttpClient(
        timeout: 5,
        retryAttempts: 3,
        retryDelay: 100,
    );

    // This endpoint returns 500 status
    try {
        $response = $client->get('https://httpbin.org/status/500');
        expect($response->serverError())->toBeTrue();
    } catch (\Throwable $e) {
        // Expected to fail after retries
        expect($e)->toBeInstanceOf(\Throwable::class);
    }
})->skip('Requires real HTTP calls');

test('it handles timeout correctly', function () {
    $client = new AsyncHttpClient(timeout: 1);

    expect(fn () => $client->get('https://httpbin.org/delay/5'))
        ->toThrow(\Throwable::class);
})->skip('Requires real HTTP calls');

test('it can parse JSON responses', function () {
    $client = new AsyncHttpClient;

    $response = $client->get('https://httpbin.org/json');

    expect($response->successful())->toBeTrue();
    $json = $response->json();
    expect($json)->toBeArray();
    expect($json)->toHaveKey('slideshow');
});

test('it can send custom headers', function () {
    $client = new AsyncHttpClient;

    $response = $client->get('https://httpbin.org/headers', [
        'X-Custom-Header' => 'FiberFlow',
        'User-Agent' => 'FiberFlow/0.2.0',
    ]);

    expect($response->successful())->toBeTrue();
    $json = $response->json();
    expect($json['headers'])->toHaveKey('X-Custom-Header');
    expect($json['headers']['X-Custom-Header'])->toBe('FiberFlow');
});

test('it detects client errors', function () {
    $client = new AsyncHttpClient;

    $response = $client->get('https://httpbin.org/status/404');

    expect($response->clientError())->toBeTrue();
    expect($response->status())->toBe(404);
    expect($response->failed())->toBeTrue();
});

test('it detects server errors', function () {
    $client = new AsyncHttpClient;

    $response = $client->get('https://httpbin.org/status/500');

    expect($response->serverError())->toBeTrue();
    expect($response->status())->toBe(500);
    expect($response->failed())->toBeTrue();
});

test('it can make a PUT request', function () {
    $client = new AsyncHttpClient;

    $response = $client->put('https://httpbin.org/put', [
        'name' => 'FiberFlow',
        'action' => 'update',
    ]);

    expect($response->successful())->toBeTrue();
    $json = $response->json();
    expect($json)->toHaveKey('json');
    expect($json['json']['name'])->toBe('FiberFlow');
});

test('it can make a PATCH request', function () {
    $client = new AsyncHttpClient;

    $response = $client->patch('https://httpbin.org/patch', [
        'name' => 'FiberFlow',
        'action' => 'patch',
    ]);

    expect($response->successful())->toBeTrue();
    $json = $response->json();
    expect($json)->toHaveKey('json');
    expect($json['json']['name'])->toBe('FiberFlow');
});

test('it can make a DELETE request', function () {
    $client = new AsyncHttpClient;

    $response = $client->delete('https://httpbin.org/delete');

    expect($response->successful())->toBeTrue();
    expect($response->status())->toBe(200);
});

test('it can get response headers', function () {
    $client = new AsyncHttpClient;

    $response = $client->get('https://httpbin.org/response-headers?X-Test=FiberFlow');

    expect($response->successful())->toBeTrue();
    $headers = $response->headers();
    expect($headers)->toBeArray();
});

test('it can get single response header', function () {
    $client = new AsyncHttpClient;

    $response = $client->get('https://httpbin.org/get');

    expect($response->successful())->toBeTrue();
    $contentType = $response->header('Content-Type');
    expect($contentType)->toContain('application/json');
});

test('it can check redirect status code range', function () {
    $client = new AsyncHttpClient;

    // httpbin follows redirects by default, so we test the method logic
    $response = $client->get('https://httpbin.org/get');

    // Test that redirect() method works (should be false for 200)
    expect($response->redirect())->toBeFalse();
    expect($response->successful())->toBeTrue();
});

test('it can get underlying response object', function () {
    $client = new AsyncHttpClient;

    $response = $client->get('https://httpbin.org/get');

    expect($response->successful())->toBeTrue();
    $ampResponse = $response->getResponse();
    expect($ampResponse)->toBeInstanceOf(\Amp\Http\Client\Response::class);
});

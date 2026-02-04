<?php

declare(strict_types=1);

namespace FiberFlow\Http;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Fiber;
use Revolt\EventLoop;

class AsyncHttpClient
{
    /**
     * The HTTP client instance.
     */
    protected \Amp\Http\Client\HttpClient $client;

    /**
     * Create a new async HTTP client instance.
     */
    public function __construct(
        protected int $timeout = 30,
        protected int $retryAttempts = 3,
        protected int $retryDelay = 1000
    ) {
        $this->client = HttpClientBuilder::buildDefault();
    }

    /**
     * Perform a GET request.
     *
     * @param array<string, string> $headers
     */
    public function get(string $url, array $headers = []): AsyncHttpResponse
    {
        return $this->request('GET', $url, ['headers' => $headers]);
    }

    /**
     * Perform a POST request.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function post(string $url, array $data = [], array $headers = []): AsyncHttpResponse
    {
        return $this->request('POST', $url, [
            'headers' => $headers,
            'body' => json_encode($data),
        ]);
    }

    /**
     * Perform a PUT request.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function put(string $url, array $data = [], array $headers = []): AsyncHttpResponse
    {
        return $this->request('PUT', $url, [
            'headers' => $headers,
            'body' => json_encode($data),
        ]);
    }

    /**
     * Perform a PATCH request.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function patch(string $url, array $data = [], array $headers = []): AsyncHttpResponse
    {
        return $this->request('PATCH', $url, [
            'headers' => $headers,
            'body' => json_encode($data),
        ]);
    }

    /**
     * Perform a DELETE request.
     *
     * @param array<string, string> $headers
     */
    public function delete(string $url, array $headers = []): AsyncHttpResponse
    {
        return $this->request('DELETE', $url, ['headers' => $headers]);
    }

    /**
     * Perform an HTTP request.
     *
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): AsyncHttpResponse
    {
        $request = $this->buildRequest($method, $url, $options);
        $retryAttempts = $options['retry_attempts'] ?? $this->retryAttempts;
        $retryDelay = $options['retry_delay'] ?? $this->retryDelay;

        return $this->requestWithRetry($request, $retryAttempts, $retryDelay);
    }

    /**
     * Build an HTTP request from options.
     *
     * @param array<string, mixed> $options
     */
    protected function buildRequest(string $method, string $url, array $options): Request
    {
        $request = new Request($url, $method);

        // Set headers
        if (isset($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                $request->setHeader($name, $value);
            }
        }

        // Set body
        if (isset($options['body'])) {
            $request->setBody($options['body']);
        }

        return $request;
    }

    /**
     * Perform request with retry logic and exponential backoff.
     */
    protected function requestWithRetry(Request $request, int $maxAttempts, int $baseDelay): AsyncHttpResponse
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            try {
                // If we're in a Fiber, suspend and resume when response arrives
                if (Fiber::getCurrent() !== null) {
                    return $this->requestAsync($request);
                }

                // Otherwise, make a blocking request
                return $this->requestSync($request);
            } catch (\Throwable $e) {
                $lastException = $e;
                $attempt++;

                // Don't retry if we've exhausted attempts
                if ($attempt >= $maxAttempts) {
                    break;
                }

                // Calculate exponential backoff delay
                $delay = $this->calculateBackoffDelay($attempt, $baseDelay);

                // Sleep before retry (in microseconds)
                usleep($delay * 1000);
            }
        }

        // All retries failed, throw the last exception
        throw $lastException ?? new \RuntimeException('Request failed with no exception');
    }

    /**
     * Calculate exponential backoff delay.
     */
    protected function calculateBackoffDelay(int $attempt, int $baseDelay): int
    {
        // Exponential backoff: baseDelay * 2^(attempt - 1)
        // With jitter to prevent thundering herd
        $exponentialDelay = $baseDelay * (2 ** ($attempt - 1));
        $jitter = rand(0, (int) ($exponentialDelay * 0.1)); // 10% jitter

        return $exponentialDelay + $jitter;
    }

    /**
     * Make an async request (suspends the current Fiber).
     */
    protected function requestAsync(Request $request): AsyncHttpResponse
    {
        $response = null;
        $exception = null;

        EventLoop::queue(function () use ($request, &$response, &$exception): void {
            try {
                $response = $this->client->request($request);
            } catch (\Throwable $e) {
                $exception = $e;
            }
        });

        // Suspend the current Fiber
        Fiber::suspend();

        if ($exception !== null) {
            throw $exception;
        }

        return new AsyncHttpResponse($response);
    }

    /**
     * Make a synchronous request (blocks).
     */
    protected function requestSync(Request $request): AsyncHttpResponse
    {
        $response = $this->client->request($request);

        return new AsyncHttpResponse($response);
    }
}


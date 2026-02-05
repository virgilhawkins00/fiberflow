<?php

declare(strict_types=1);

namespace FiberFlow\Http;

use Amp\Http\Client\Response;

class AsyncHttpResponse
{
    /**
     * Create a new async HTTP response instance.
     */
    public function __construct(
        protected Response $response,
    ) {}

    /**
     * Get the response status code.
     */
    public function status(): int
    {
        return $this->response->getStatus();
    }

    /**
     * Get the response body as a string.
     */
    public function body(): string
    {
        return $this->response->getBody()->buffer();
    }

    /**
     * Get the response body as JSON.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        return json_decode($this->body(), true) ?? [];
    }

    /**
     * Get a response header.
     */
    public function header(string $name): ?string
    {
        return $this->response->getHeader($name);
    }

    /**
     * Get all response headers.
     *
     * @return array<string, array<string>>
     */
    public function headers(): array
    {
        return $this->response->getHeaders();
    }

    /**
     * Check if the response was successful (2xx status code).
     */
    public function successful(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    /**
     * Check if the response was a redirect (3xx status code).
     */
    public function redirect(): bool
    {
        return $this->status() >= 300 && $this->status() < 400;
    }

    /**
     * Check if the response was a client error (4xx status code).
     */
    public function clientError(): bool
    {
        return $this->status() >= 400 && $this->status() < 500;
    }

    /**
     * Check if the response was a server error (5xx status code).
     */
    public function serverError(): bool
    {
        return $this->status() >= 500;
    }

    /**
     * Check if the response failed (4xx or 5xx status code).
     */
    public function failed(): bool
    {
        return $this->clientError() || $this->serverError();
    }

    /**
     * Get the underlying Amp response.
     */
    public function getResponse(): Response
    {
        return $this->response;
    }
}

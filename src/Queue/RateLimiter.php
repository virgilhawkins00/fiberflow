<?php

declare(strict_types=1);

namespace FiberFlow\Queue;

/**
 * Token bucket rate limiter for FiberFlow.
 *
 * Implements the token bucket algorithm for rate limiting job processing.
 */
class RateLimiter
{
    /**
     * Current number of tokens.
     */
    protected float $tokens;

    /**
     * Last refill timestamp.
     */
    protected float $lastRefill;

    /**
     * Create a new rate limiter instance.
     *
     * @param int $maxTokens Maximum number of tokens (burst capacity)
     * @param float $refillRate Tokens added per second
     */
    public function __construct(
        protected int $maxTokens,
        protected float $refillRate,
    ) {
        $this->tokens = $maxTokens;
        $this->lastRefill = microtime(true);
    }

    /**
     * Attempt to consume tokens.
     *
     * @param int $tokens Number of tokens to consume
     *
     * @return bool True if tokens were consumed, false otherwise
     */
    public function attempt(int $tokens = 1): bool
    {
        $this->refill();

        if ($this->tokens >= $tokens) {
            $this->tokens -= $tokens;

            return true;
        }

        return false;
    }

    /**
     * Wait until tokens are available and consume them.
     *
     * @param int $tokens Number of tokens to consume
     */
    public function wait(int $tokens = 1): void
    {
        while (! $this->attempt($tokens)) {
            // Calculate wait time
            $tokensNeeded = $tokens - $this->tokens;
            $waitTime = $tokensNeeded / $this->refillRate;

            // Wait for tokens to refill
            usleep((int) ($waitTime * 1000000));
        }
    }

    /**
     * Refill tokens based on elapsed time.
     */
    protected function refill(): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->lastRefill;

        // Add tokens based on elapsed time
        $tokensToAdd = $elapsed * $this->refillRate;
        $this->tokens = min($this->maxTokens, $this->tokens + $tokensToAdd);

        $this->lastRefill = $now;
    }

    /**
     * Get the current number of tokens.
     */
    public function getTokens(): float
    {
        $this->refill();

        return $this->tokens;
    }

    /**
     * Get the maximum number of tokens.
     */
    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    /**
     * Get the refill rate (tokens per second).
     */
    public function getRefillRate(): float
    {
        return $this->refillRate;
    }

    /**
     * Reset the rate limiter.
     */
    public function reset(): void
    {
        $this->tokens = $this->maxTokens;
        $this->lastRefill = microtime(true);
    }

    /**
     * Get the time until the next token is available (in seconds).
     */
    public function getWaitTime(int $tokens = 1): float
    {
        $this->refill();

        if ($this->tokens >= $tokens) {
            return 0.0;
        }

        $tokensNeeded = $tokens - $this->tokens;

        return $tokensNeeded / $this->refillRate;
    }
}

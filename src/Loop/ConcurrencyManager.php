<?php

declare(strict_types=1);

namespace FiberFlow\Loop;

use Fiber;
use FiberFlow\Exceptions\ConcurrencyLimitException;

class ConcurrencyManager
{
    /**
     * Active Fibers currently running.
     *
     * @var array<int, Fiber>
     */
    protected array $activeFibers = [];

    /**
     * Metrics for monitoring.
     *
     * @var array<string, int>
     */
    protected array $metrics = [
        'total_spawned' => 0,
        'total_completed' => 0,
        'total_failed' => 0,
    ];

    /**
     * Create a new concurrency manager instance.
     */
    public function __construct(
        protected int $maxConcurrency = 50
    ) {
    }

    /**
     * Spawn a new Fiber with the given callback.
     *
     * @throws ConcurrencyLimitException
     */
    public function spawn(callable $callback): Fiber
    {
        if ($this->isFull()) {
            throw new ConcurrencyLimitException(
                "Maximum concurrency limit of {$this->maxConcurrency} reached"
            );
        }

        $fiber = new Fiber(function () use ($callback): void {
            try {
                $callback();
                $this->metrics['total_completed']++;
            } catch (\Throwable $e) {
                $this->metrics['total_failed']++;
                throw $e;
            } finally {
                $this->remove(Fiber::getCurrent());
            }
        });

        $this->activeFibers[] = $fiber;
        $this->metrics['total_spawned']++;

        $fiber->start();

        return $fiber;
    }

    /**
     * Check if the concurrency limit has been reached.
     */
    public function isFull(): bool
    {
        $this->cleanupTerminatedFibers();

        return count($this->activeFibers) >= $this->maxConcurrency;
    }

    /**
     * Get the number of active Fibers.
     */
    public function getActiveCount(): int
    {
        $this->cleanupTerminatedFibers();

        return count($this->activeFibers);
    }

    /**
     * Get the available slots for new Fibers.
     */
    public function getAvailableSlots(): int
    {
        return max(0, $this->maxConcurrency - $this->getActiveCount());
    }

    /**
     * Remove a Fiber from the active list.
     */
    public function remove(?Fiber $fiber): void
    {
        if ($fiber === null) {
            return;
        }

        $this->activeFibers = array_filter(
            $this->activeFibers,
            fn (Fiber $f) => $f !== $fiber
        );
    }

    /**
     * Clean up terminated Fibers from the active list.
     */
    protected function cleanupTerminatedFibers(): void
    {
        $this->activeFibers = array_filter(
            $this->activeFibers,
            fn (Fiber $fiber) => !$fiber->isTerminated()
        );
    }

    /**
     * Get all active Fibers.
     *
     * @return array<int, Fiber>
     */
    public function getActiveFibers(): array
    {
        $this->cleanupTerminatedFibers();

        return $this->activeFibers;
    }

    /**
     * Get metrics for monitoring.
     *
     * @return array<string, int>
     */
    public function getMetrics(): array
    {
        return array_merge($this->metrics, [
            'active_fibers' => $this->getActiveCount(),
            'available_slots' => $this->getAvailableSlots(),
        ]);
    }

    /**
     * Wait for all active Fibers to complete.
     */
    public function waitForAll(): void
    {
        while ($this->getActiveCount() > 0) {
            usleep(10000); // 10ms
        }
    }

    /**
     * Terminate all active Fibers.
     */
    public function terminateAll(): void
    {
        foreach ($this->activeFibers as $fiber) {
            if (!$fiber->isTerminated()) {
                // Fibers will be cleaned up by garbage collector
            }
        }

        $this->activeFibers = [];
    }
}


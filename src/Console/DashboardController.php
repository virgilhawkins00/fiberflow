<?php

declare(strict_types=1);

namespace FiberFlow\Console;

/**
 * Controls dashboard state and worker operations.
 */
class DashboardController
{
    /**
     * Worker state.
     */
    protected string $state = 'running';

    /**
     * Available states.
     */
    protected const STATE_RUNNING = 'running';
    protected const STATE_PAUSED = 'paused';
    protected const STATE_STOPPING = 'stopping';
    protected const STATE_STOPPED = 'stopped';

    /**
     * Pause the worker.
     */
    public function pause(): void
    {
        if ($this->state === self::STATE_RUNNING) {
            $this->state = self::STATE_PAUSED;
        }
    }

    /**
     * Resume the worker.
     */
    public function resume(): void
    {
        if ($this->state === self::STATE_PAUSED) {
            $this->state = self::STATE_RUNNING;
        }
    }

    /**
     * Stop the worker gracefully.
     */
    public function stop(): void
    {
        $this->state = self::STATE_STOPPING;
    }

    /**
     * Force stop the worker.
     */
    public function forceStop(): void
    {
        $this->state = self::STATE_STOPPED;
    }

    /**
     * Check if the worker is running.
     */
    public function isRunning(): bool
    {
        return $this->state === self::STATE_RUNNING;
    }

    /**
     * Check if the worker is paused.
     */
    public function isPaused(): bool
    {
        return $this->state === self::STATE_PAUSED;
    }

    /**
     * Check if the worker is stopping.
     */
    public function isStopping(): bool
    {
        return $this->state === self::STATE_STOPPING;
    }

    /**
     * Check if the worker is stopped.
     */
    public function isStopped(): bool
    {
        return $this->state === self::STATE_STOPPED;
    }

    /**
     * Get the current state.
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Get state display name.
     */
    public function getStateDisplay(): string
    {
        return match ($this->state) {
            self::STATE_RUNNING => '🟢 RUNNING',
            self::STATE_PAUSED => '🟡 PAUSED',
            self::STATE_STOPPING => '🟠 STOPPING',
            self::STATE_STOPPED => '🔴 STOPPED',
            default => '⚪ UNKNOWN',
        };
    }

    /**
     * Handle keyboard input.
     */
    public function handleInput(string $input): void
    {
        $input = strtolower(trim($input));

        match ($input) {
            'p' => $this->pause(),
            'r' => $this->resume(),
            's' => $this->stop(),
            'q' => $this->forceStop(),
            default => null,
        };
    }

    /**
     * Get help text for controls.
     */
    public function getHelpText(): string
    {
        return <<<'HELP'
Controls:
  [P] Pause   [R] Resume   [S] Stop Gracefully   [Q] Quit Immediately
HELP;
    }
}


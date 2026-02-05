<?php

declare(strict_types=1);

namespace FiberFlow\Console;

/**
 * Handles interactive controls for the FiberFlow dashboard.
 */
class InteractiveControls
{
    /**
     * Worker state.
     */
    protected string $state = 'running';

    /**
     * Whether to stop the worker.
     */
    protected bool $shouldStop = false;

    /**
     * Create a new interactive controls instance.
     */
    public function __construct()
    {
        $this->setupSignalHandlers();
    }

    /**
     * Setup signal handlers for graceful shutdown.
     */
    protected function setupSignalHandlers(): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);

            pcntl_signal(SIGTERM, function () {
                $this->stop();
            });

            pcntl_signal(SIGINT, function () {
                $this->stop();
            });

            pcntl_signal(SIGUSR1, function () {
                $this->pause();
            });

            pcntl_signal(SIGUSR2, function () {
                $this->resume();
            });
        }
    }

    /**
     * Pause the worker.
     */
    public function pause(): void
    {
        $this->state = 'paused';
    }

    /**
     * Resume the worker.
     */
    public function resume(): void
    {
        $this->state = 'running';
    }

    /**
     * Stop the worker.
     */
    public function stop(): void
    {
        $this->state = 'stopping';
        $this->shouldStop = true;
    }

    /**
     * Check if the worker is paused.
     */
    public function isPaused(): bool
    {
        return $this->state === 'paused';
    }

    /**
     * Check if the worker is running.
     */
    public function isRunning(): bool
    {
        return $this->state === 'running';
    }

    /**
     * Check if the worker should stop.
     */
    public function shouldStop(): bool
    {
        return $this->shouldStop;
    }

    /**
     * Get the current state.
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Get available keyboard commands.
     *
     * @return array<string, string>
     */
    public function getCommands(): array
    {
        return [
            'p' => 'Pause worker',
            'r' => 'Resume worker',
            's' => 'Stop worker',
            'q' => 'Quit (same as stop)',
            'h' => 'Show help',
        ];
    }

    /**
     * Process keyboard input.
     */
    public function processInput(string $input): ?string
    {
        return match (strtolower(trim($input))) {
            'p' => $this->handlePause(),
            'r' => $this->handleResume(),
            's', 'q' => $this->handleStop(),
            'h' => $this->handleHelp(),
            default => null,
        };
    }

    /**
     * Handle pause command.
     */
    protected function handlePause(): string
    {
        if ($this->isPaused()) {
            return 'Worker is already paused';
        }

        $this->pause();

        return 'Worker paused. Press "r" to resume.';
    }

    /**
     * Handle resume command.
     */
    protected function handleResume(): string
    {
        if (! $this->isPaused()) {
            return 'Worker is already running';
        }

        $this->resume();

        return 'Worker resumed';
    }

    /**
     * Handle stop command.
     */
    protected function handleStop(): string
    {
        $this->stop();

        return 'Stopping worker gracefully...';
    }

    /**
     * Handle help command.
     */
    protected function handleHelp(): string
    {
        $commands = $this->getCommands();
        $help = "Available commands:\n";

        foreach ($commands as $key => $description) {
            $help .= "  {$key} - {$description}\n";
        }

        return $help;
    }
}

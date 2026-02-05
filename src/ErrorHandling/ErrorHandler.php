<?php

declare(strict_types=1);

namespace FiberFlow\ErrorHandling;

use FiberFlow\Exceptions\FiberCrashException;
use FiberFlow\Metrics\MetricsCollector;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles errors and exceptions in FiberFlow workers.
 */
class ErrorHandler
{
    /**
     * Error handlers by exception type.
     *
     * @var array<string, callable>
     */
    protected array $handlers = [];

    /**
     * Create a new error handler instance.
     */
    public function __construct(
        protected ?MetricsCollector $metrics = null,
    ) {
        $this->registerDefaultHandlers();
    }

    /**
     * Register default error handlers.
     */
    protected function registerDefaultHandlers(): void
    {
        // Handle Fiber crashes
        $this->register(FiberCrashException::class, function (FiberCrashException $e) {
            Log::error('Fiber crashed', [
                'fiber_id' => $e->getFiberId(),
                'job_class' => $e->getJobClass(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->metrics?->recordFiberFailed();
            $this->metrics?->recordJobFailed();
        });

        // Handle generic exceptions
        $this->register(Throwable::class, function (Throwable $e) {
            Log::error('Unhandled exception in FiberFlow', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        });
    }

    /**
     * Register an error handler for a specific exception type.
     */
    public function register(string $exceptionClass, callable $handler): void
    {
        $this->handlers[$exceptionClass] = $handler;
    }

    /**
     * Handle an exception.
     */
    public function handle(Throwable $exception): void
    {
        $exceptionClass = get_class($exception);

        // Try to find a specific handler
        if (isset($this->handlers[$exceptionClass])) {
            $this->handlers[$exceptionClass]($exception);

            return;
        }

        // Try to find a parent class handler
        foreach ($this->handlers as $class => $handler) {
            if ($exception instanceof $class) {
                $handler($exception);

                return;
            }
        }

        // Fallback to generic Throwable handler
        if (isset($this->handlers[Throwable::class])) {
            $this->handlers[Throwable::class]($exception);
        }
    }

    /**
     * Handle a Fiber crash.
     */
    public function handleFiberCrash(\Fiber $fiber, object $job, Throwable $exception): void
    {
        $crashException = FiberCrashException::fromFiber($fiber, $job, $exception);
        $this->handle($crashException);
    }

    /**
     * Wrap a callable with error handling.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T|null
     */
    public function wrap(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            $this->handle($e);

            return null;
        }
    }

    /**
     * Check if a handler is registered for an exception type.
     */
    public function hasHandler(string $exceptionClass): bool
    {
        return isset($this->handlers[$exceptionClass]);
    }

    /**
     * Remove a handler for an exception type.
     */
    public function unregister(string $exceptionClass): void
    {
        unset($this->handlers[$exceptionClass]);
    }

    /**
     * Get all registered handlers.
     *
     * @return array<string, callable>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }
}

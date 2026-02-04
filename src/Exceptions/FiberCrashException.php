<?php

declare(strict_types=1);

namespace FiberFlow\Exceptions;

use Throwable;

/**
 * Exception thrown when a Fiber crashes unexpectedly.
 */
class FiberCrashException extends FiberException
{
    /**
     * Create a new Fiber crash exception.
     */
    public function __construct(
        string $message = 'Fiber crashed unexpectedly',
        int $code = 0,
        ?Throwable $previous = null,
        protected ?string $fiberId = null,
        protected ?string $jobClass = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the Fiber ID.
     */
    public function getFiberId(): ?string
    {
        return $this->fiberId;
    }

    /**
     * Get the job class name.
     */
    public function getJobClass(): ?string
    {
        return $this->jobClass;
    }

    /**
     * Create exception from a Fiber and job.
     */
    public static function fromFiber(\Fiber $fiber, object $job, Throwable $previous): self
    {
        $fiberId = spl_object_hash($fiber);
        $jobClass = get_class($job);

        return new self(
            message: "Fiber {$fiberId} crashed while processing job {$jobClass}: {$previous->getMessage()}",
            previous: $previous,
            fiberId: $fiberId,
            jobClass: $jobClass
        );
    }
}


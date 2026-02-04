<?php

declare(strict_types=1);

namespace FiberFlow\Coroutine;

use Fiber;
use Illuminate\Container\Container;
use WeakMap;

class SandboxManager
{
    /**
     * Map of Fibers to their sandboxed containers.
     *
     * @var WeakMap<Fiber, Container>
     */
    protected WeakMap $fiberContainers;

    /**
     * The base application container.
     */
    protected Container $baseContainer;

    /**
     * Whether sandboxing is enabled.
     */
    protected bool $enabled;

    /**
     * Container pollution detector.
     */
    protected ?ContainerPollutionDetector $pollutionDetector = null;

    /**
     * Create a new sandbox manager instance.
     */
    public function __construct(Container $container, ?ContainerPollutionDetector $detector = null)
    {
        $this->baseContainer = $container;
        $this->fiberContainers = new WeakMap();
        $this->enabled = config('fiberflow.sandbox_enabled', true);
        $this->pollutionDetector = $detector ?? new ContainerPollutionDetector();
    }

    /**
     * Create a new sandboxed container for the current Fiber.
     */
    public function createSandbox(): Container
    {
        if (!$this->enabled) {
            return $this->baseContainer;
        }

        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            return $this->baseContainer;
        }

        // Clone the base container to create an isolated sandbox
        $sandbox = clone $this->baseContainer;

        // Store the mapping
        $this->fiberContainers[$fiber] = $sandbox;

        // Take initial snapshot for pollution detection
        if ($this->pollutionDetector !== null) {
            $this->pollutionDetector->takeSnapshot($sandbox);
        }

        return $sandbox;
    }

    /**
     * Get the container for the current Fiber.
     */
    public function getCurrentContainer(): Container
    {
        if (!$this->enabled) {
            return $this->baseContainer;
        }

        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            return $this->baseContainer;
        }

        return $this->fiberContainers[$fiber] ?? $this->baseContainer;
    }

    /**
     * Destroy the sandbox for the current Fiber.
     */
    public function destroySandbox(): void
    {
        if (!$this->enabled) {
            return;
        }

        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            return;
        }

        // WeakMap automatically handles cleanup when Fiber is garbage collected
        // But we can explicitly unset if needed
        if (isset($this->fiberContainers[$fiber])) {
            unset($this->fiberContainers[$fiber]);
        }
    }

    /**
     * Check if the current Fiber has a sandbox.
     */
    public function hasSandbox(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            return false;
        }

        return isset($this->fiberContainers[$fiber]);
    }

    /**
     * Get the number of active sandboxes.
     */
    public function getActiveSandboxCount(): int
    {
        if (!$this->enabled) {
            return 0;
        }

        // WeakMap doesn't have a count method, so we iterate
        $count = 0;
        foreach ($this->fiberContainers as $fiber => $container) {
            $count++;
        }

        return $count;
    }

    /**
     * Enable or disable sandboxing.
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Check if sandboxing is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get the base container.
     */
    public function getBaseContainer(): Container
    {
        return $this->baseContainer;
    }

    /**
     * Verify container integrity (check for pollution).
     *
     * @throws \FiberFlow\Exceptions\ContainerPollutionException
     */
    public function verifyIntegrity(): void
    {
        if (!$this->enabled || $this->pollutionDetector === null) {
            return;
        }

        $container = $this->getCurrentContainer();
        $this->pollutionDetector->verify($container);
    }

    /**
     * Get the pollution detector.
     */
    public function getPollutionDetector(): ?ContainerPollutionDetector
    {
        return $this->pollutionDetector;
    }
}


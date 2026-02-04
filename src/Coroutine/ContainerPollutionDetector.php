<?php

declare(strict_types=1);

namespace FiberFlow\Coroutine;

use FiberFlow\Exceptions\ContainerPollutionException;
use Illuminate\Container\Container;

/**
 * Detects container pollution between Fibers.
 *
 * Monitors state changes in the container to ensure
 * that Fibers don't leak state to each other.
 */
class ContainerPollutionDetector
{
    /**
     * Services that should be isolated per Fiber.
     *
     * @var array<string>
     */
    protected array $isolatedServices = [
        'auth',
        'auth.driver',
        'session',
        'session.store',
        'cache',
        'cache.store',
        'request',
        'cookie',
    ];

    /**
     * Snapshots of container state per Fiber.
     *
     * @var \WeakMap<\Fiber, array<string, mixed>>
     */
    protected \WeakMap $snapshots;

    /**
     * Whether detection is enabled.
     */
    protected bool $enabled;

    /**
     * Create a new pollution detector instance.
     */
    public function __construct()
    {
        $this->snapshots = new \WeakMap();
        $this->enabled = config('fiberflow.pollution_detection.enabled', true);
    }

    /**
     * Take a snapshot of the container state for the current Fiber.
     */
    public function takeSnapshot(Container $container): void
    {
        if (!$this->enabled) {
            return;
        }

        $fiber = \Fiber::getCurrent();

        if ($fiber === null) {
            return;
        }

        $snapshot = [];

        foreach ($this->isolatedServices as $service) {
            if ($container->bound($service)) {
                $instance = $container->make($service);
                $snapshot[$service] = $this->captureState($instance);
            }
        }

        $this->snapshots[$fiber] = $snapshot;
    }

    /**
     * Verify that the container state hasn't been polluted.
     *
     * @throws ContainerPollutionException
     */
    public function verify(Container $container): void
    {
        if (!$this->enabled) {
            return;
        }

        $fiber = \Fiber::getCurrent();

        if ($fiber === null) {
            return;
        }

        if (!isset($this->snapshots[$fiber])) {
            return;
        }

        $originalSnapshot = $this->snapshots[$fiber];
        $violations = [];

        foreach ($this->isolatedServices as $service) {
            if (!$container->bound($service)) {
                continue;
            }

            $instance = $container->make($service);
            $currentState = $this->captureState($instance);

            if (!isset($originalSnapshot[$service])) {
                continue;
            }

            $originalState = $originalSnapshot[$service];

            if ($this->hasStateChanged($originalState, $currentState)) {
                $violations[] = [
                    'service' => $service,
                    'original' => $originalState,
                    'current' => $currentState,
                ];
            }
        }

        if (!empty($violations)) {
            throw new ContainerPollutionException(
                'Container pollution detected: ' . json_encode($violations)
            );
        }
    }

    /**
     * Capture the state of a service instance.
     *
     * @return array<string, mixed>
     */
    protected function captureState(mixed $instance): array
    {
        if (is_object($instance)) {
            return [
                'class' => get_class($instance),
                'hash' => spl_object_hash($instance),
                'properties' => $this->getObjectProperties($instance),
            ];
        }

        return [
            'type' => gettype($instance),
            'value' => $instance,
        ];
    }

    /**
     * Get relevant properties from an object.
     *
     * @return array<string, mixed>
     */
    protected function getObjectProperties(object $instance): array
    {
        $properties = [];

        // For Auth, capture user ID
        if (method_exists($instance, 'id')) {
            $properties['user_id'] = $instance->id();
        }

        // For Session, capture session ID
        if (method_exists($instance, 'getId')) {
            $properties['session_id'] = $instance->getId();
        }

        return $properties;
    }

    /**
     * Check if state has changed between snapshots.
     */
    protected function hasStateChanged(array $original, array $current): bool
    {
        // Compare object hashes
        if (isset($original['hash']) && isset($current['hash'])) {
            if ($original['hash'] !== $current['hash']) {
                return true;
            }
        }

        // Compare properties
        if (isset($original['properties']) && isset($current['properties'])) {
            foreach ($original['properties'] as $key => $value) {
                if (!isset($current['properties'][$key])) {
                    return true;
                }

                if ($current['properties'][$key] !== $value) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add a service to the isolation list.
     */
    public function addIsolatedService(string $service): void
    {
        if (!in_array($service, $this->isolatedServices, true)) {
            $this->isolatedServices[] = $service;
        }
    }

    /**
     * Enable or disable pollution detection.
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Check if pollution detection is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}


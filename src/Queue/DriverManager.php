<?php

declare(strict_types=1);

namespace FiberFlow\Queue;

use FiberFlow\Queue\Contracts\AsyncQueueDriver;
use FiberFlow\Queue\Drivers\DatabaseQueueDriver;
use FiberFlow\Queue\Drivers\RabbitMqQueueDriver;
use FiberFlow\Queue\Drivers\SqsQueueDriver;

/**
 * Queue driver manager for FiberFlow.
 *
 * Manages registration and creation of queue drivers.
 */
class DriverManager
{
    /**
     * Registered drivers.
     *
     * @var array<string, class-string<AsyncQueueDriver>>
     */
    protected array $drivers = [];

    /**
     * Driver instances.
     *
     * @var array<string, AsyncQueueDriver>
     */
    protected array $instances = [];

    /**
     * Create a new driver manager.
     */
    public function __construct()
    {
        $this->registerDefaultDrivers();
    }

    /**
     * Register default drivers.
     */
    protected function registerDefaultDrivers(): void
    {
        $this->register('database', DatabaseQueueDriver::class);
        $this->register('sqs', SqsQueueDriver::class);
        $this->register('rabbitmq', RabbitMqQueueDriver::class);
    }

    /**
     * Register a custom driver.
     *
     * @param string $name Driver name
     * @param class-string<AsyncQueueDriver> $class Driver class
     */
    public function register(string $name, string $class): void
    {
        $this->drivers[$name] = $class;
    }

    /**
     * Create or get a driver instance.
     *
     * @param string $name Driver name
     * @param array<string, mixed> $config Driver configuration
     */
    public function driver(string $name, array $config = []): AsyncQueueDriver
    {
        $key = $name.'_'.md5(serialize($config));

        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        if (! isset($this->drivers[$name])) {
            throw new \InvalidArgumentException("Queue driver [{$name}] not registered.");
        }

        $class = $this->drivers[$name];
        $this->instances[$key] = new $class($config);

        return $this->instances[$key];
    }

    /**
     * Check if a driver is registered.
     */
    public function hasDriver(string $name): bool
    {
        return isset($this->drivers[$name]);
    }

    /**
     * Get all registered driver names.
     *
     * @return array<int, string>
     */
    public function getDriverNames(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Close all driver instances.
     */
    public function closeAll(): void
    {
        foreach ($this->instances as $instance) {
            $instance->close();
        }

        $this->instances = [];
    }

    /**
     * Extend the manager with a custom driver creator.
     *
     * @param string $name Driver name
     * @param callable $callback Callback that returns AsyncQueueDriver
     */
    public function extend(string $name, callable $callback): void
    {
        $this->drivers[$name] = $callback;
    }
}

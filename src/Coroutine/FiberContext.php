<?php

declare(strict_types=1);

namespace FiberFlow\Coroutine;

use Fiber;
use WeakMap;

/**
 * Provides Fiber-local storage for context data.
 */
class FiberContext
{
    /**
     * Map of Fibers to their context data.
     *
     * @var WeakMap<Fiber, array<string, mixed>>
     */
    protected static WeakMap $contexts;

    /**
     * Initialize the context storage.
     */
    protected static function initialize(): void
    {
        if (!isset(self::$contexts)) {
            self::$contexts = new WeakMap();
        }
    }

    /**
     * Set a value in the current Fiber's context.
     *
     * @param mixed $value
     */
    public static function set(string $key, mixed $value): void
    {
        self::initialize();

        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            return;
        }

        if (!isset(self::$contexts[$fiber])) {
            self::$contexts[$fiber] = [];
        }

        self::$contexts[$fiber][$key] = $value;
    }

    /**
     * Get a value from the current Fiber's context.
     *
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::initialize();

        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            return $default;
        }

        return self::$contexts[$fiber][$key] ?? $default;
    }

    /**
     * Check if a key exists in the current Fiber's context.
     */
    public static function has(string $key): bool
    {
        self::initialize();

        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            return false;
        }

        return isset(self::$contexts[$fiber][$key]);
    }

    /**
     * Remove a value from the current Fiber's context.
     */
    public static function forget(string $key): void
    {
        self::initialize();

        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            return;
        }

        if (isset(self::$contexts[$fiber])) {
            $context = self::$contexts[$fiber];
            if (isset($context[$key])) {
                unset($context[$key]);
                self::$contexts[$fiber] = $context;
            }
        }
    }

    /**
     * Get all context data for the current Fiber.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        self::initialize();

        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            return [];
        }

        return self::$contexts[$fiber] ?? [];
    }

    /**
     * Clear all context data for the current Fiber.
     */
    public static function clear(): void
    {
        self::initialize();

        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            return;
        }

        if (isset(self::$contexts[$fiber])) {
            unset(self::$contexts[$fiber]);
        }
    }
}


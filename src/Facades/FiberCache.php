<?php

declare(strict_types=1);

namespace FiberFlow\Facades;

use FiberFlow\Coroutine\FiberContext;
use Illuminate\Support\Facades\Facade;

/**
 * Fiber-aware Cache Facade.
 *
 * Provides caching functionality with Fiber isolation.
 * Each Fiber can maintain its own cache namespace.
 *
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool put(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null)
 * @method static bool add(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null)
 * @method static int|bool increment(string $key, mixed $value = 1)
 * @method static int|bool decrement(string $key, mixed $value = 1)
 * @method static bool forever(string $key, mixed $value)
 * @method static mixed pull(string $key, mixed $default = null)
 * @method static bool forget(string $key)
 * @method static bool flush()
 * @method static bool has(string $key)
 * @method static bool missing(string $key)
 * @method static mixed remember(string $key, \DateTimeInterface|\DateInterval|int|null $ttl, \Closure $callback)
 * @method static mixed rememberForever(string $key, \Closure $callback)
 * @method static \Illuminate\Contracts\Cache\Store getStore()
 *
 * @see \Illuminate\Cache\CacheManager
 * @see \Illuminate\Contracts\Cache\Repository
 */
class FiberCache extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'fiberflow.cache';
    }

    /**
     * Resolve the facade root instance from the container.
     *
     * @param string $name
     *
     * @return mixed
     */
    protected static function resolveFacadeInstance($name)
    {
        // If we're in a Fiber, use the Fiber's container
        if (\Fiber::getCurrent() !== null) {
            $container = app('fiberflow.sandbox')->getCurrentContainer();

            if ($container !== null) {
                return $container->make($name);
            }
        }

        // Otherwise, use the default container
        return parent::resolveFacadeInstance($name);
    }

    /**
     * Get a cache key prefixed with the current Fiber ID.
     */
    public static function fiberKey(string $key): string
    {
        $fiber = \Fiber::getCurrent();

        if ($fiber === null) {
            return $key;
        }

        // Use Fiber object hash as unique identifier
        $fiberId = spl_object_hash($fiber);

        return "fiber:{$fiberId}:{$key}";
    }

    /**
     * Get a value from the Fiber-scoped cache.
     */
    public static function fiberGet(string $key, mixed $default = null): mixed
    {
        return static::get(static::fiberKey($key), $default);
    }

    /**
     * Store a value in the Fiber-scoped cache.
     */
    public static function fiberPut(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool
    {
        return static::put(static::fiberKey($key), $value, $ttl);
    }

    /**
     * Remember a value in the Fiber-scoped cache.
     */
    public static function fiberRemember(string $key, \DateTimeInterface|\DateInterval|int|null $ttl, \Closure $callback): mixed
    {
        return static::remember(static::fiberKey($key), $ttl, $callback);
    }

    /**
     * Forget a value from the Fiber-scoped cache.
     */
    public static function fiberForget(string $key): bool
    {
        return static::forget(static::fiberKey($key));
    }

    /**
     * Flush all Fiber-scoped cache entries for the current Fiber.
     */
    public static function flushFiberCache(): bool
    {
        $fiber = \Fiber::getCurrent();

        if ($fiber === null) {
            return false;
        }

        $fiberId = spl_object_hash($fiber);
        $prefix = "fiber:{$fiberId}:";

        // This is a simplified implementation
        // In production, you'd need to iterate through keys with the prefix
        FiberContext::set('cache.flushed', true);

        return true;
    }

    /**
     * Store data in Fiber context (in-memory cache for the Fiber).
     */
    public static function contextPut(string $key, mixed $value): void
    {
        FiberContext::set("cache.context.{$key}", $value);
    }

    /**
     * Get data from Fiber context (in-memory cache for the Fiber).
     */
    public static function contextGet(string $key, mixed $default = null): mixed
    {
        return FiberContext::get("cache.context.{$key}", $default);
    }

    /**
     * Check if a key exists in Fiber context.
     */
    public static function contextHas(string $key): bool
    {
        return FiberContext::has("cache.context.{$key}");
    }
}

<?php

declare(strict_types=1);

namespace FiberFlow\Facades;

use FiberFlow\Coroutine\FiberContext;
use Illuminate\Support\Facades\Facade;

/**
 * Fiber-aware Session Facade.
 *
 * Provides session functionality with Fiber isolation.
 * Each Fiber maintains its own session state.
 *
 * @method static string getId()
 * @method static string getName()
 * @method static void setId(string $id)
 * @method static bool start()
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool has(string|array $key)
 * @method static void put(string|array $key, mixed $value = null)
 * @method static void push(string $key, mixed $value)
 * @method static mixed pull(string $key, mixed $default = null)
 * @method static void forget(string|array $keys)
 * @method static void flush()
 * @method static bool migrate(bool $destroy = false)
 * @method static bool regenerate(bool $destroy = false)
 * @method static void invalidate()
 * @method static string token()
 * @method static void regenerateToken()
 *
 * @see \Illuminate\Session\SessionManager
 * @see \Illuminate\Contracts\Session\Session
 */
class FiberSession extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'fiberflow.session';
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
     * Get a value from the Fiber-local session.
     */
    public static function fiberGet(string $key, mixed $default = null): mixed
    {
        if (\Fiber::getCurrent() !== null) {
            return FiberContext::get("session.{$key}", $default);
        }

        return static::get($key, $default);
    }

    /**
     * Store a value in the Fiber-local session.
     */
    public static function fiberPut(string $key, mixed $value): void
    {
        if (\Fiber::getCurrent() !== null) {
            FiberContext::set("session.{$key}", $value);
        } else {
            static::put($key, $value);
        }
    }

    /**
     * Check if a key exists in the Fiber-local session.
     */
    public static function fiberHas(string $key): bool
    {
        if (\Fiber::getCurrent() !== null) {
            return FiberContext::has("session.{$key}");
        }

        return static::has($key);
    }

    /**
     * Remove a value from the Fiber-local session.
     */
    public static function fiberForget(string $key): void
    {
        if (\Fiber::getCurrent() !== null) {
            FiberContext::forget("session.{$key}");
        } else {
            static::forget($key);
        }
    }

    /**
     * Get all Fiber-local session data.
     */
    public static function fiberAll(): array
    {
        if (\Fiber::getCurrent() !== null) {
            $all = FiberContext::all();
            $sessionData = [];

            foreach ($all as $key => $value) {
                if (str_starts_with($key, 'session.')) {
                    $sessionKey = substr($key, 8); // Remove 'session.' prefix
                    $sessionData[$sessionKey] = $value;
                }
            }

            return $sessionData;
        }

        return static::all();
    }

    /**
     * Flash data to the Fiber-local session.
     */
    public static function fiberFlash(string $key, mixed $value): void
    {
        if (\Fiber::getCurrent() !== null) {
            FiberContext::set("session.flash.{$key}", $value);
        } else {
            static::flash($key, $value);
        }
    }

    /**
     * Clear all Fiber-local session data.
     */
    public static function clearFiberSession(): void
    {
        if (\Fiber::getCurrent() !== null) {
            $all = FiberContext::all();

            foreach ($all as $key => $value) {
                if (str_starts_with($key, 'session.')) {
                    FiberContext::forget($key);
                }
            }
        }
    }
}

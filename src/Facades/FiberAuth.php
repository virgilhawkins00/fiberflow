<?php

declare(strict_types=1);

namespace FiberFlow\Facades;

use FiberFlow\Coroutine\FiberContext;
use Illuminate\Support\Facades\Facade;

/**
 * Fiber-aware Auth Facade.
 *
 * Provides authentication functionality with Fiber isolation.
 * Each Fiber maintains its own authentication state.
 *
 * @method static \Illuminate\Contracts\Auth\Guard|\Illuminate\Contracts\Auth\StatefulGuard guard(string|null $name = null)
 * @method static void setUser(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static \Illuminate\Contracts\Auth\Authenticatable|null user()
 * @method static int|string|null id()
 * @method static bool check()
 * @method static bool guest()
 * @method static \Illuminate\Contracts\Auth\Authenticatable|null authenticate()
 * @method static bool attempt(array $credentials = [], bool $remember = false)
 * @method static bool once(array $credentials = [])
 * @method static void login(\Illuminate\Contracts\Auth\Authenticatable $user, bool $remember = false)
 * @method static \Illuminate\Contracts\Auth\Authenticatable loginUsingId(mixed $id, bool $remember = false)
 * @method static bool onceUsingId(mixed $id)
 * @method static bool viaRemember()
 * @method static void logout()
 *
 * @see \Illuminate\Auth\AuthManager
 * @see \Illuminate\Contracts\Auth\Guard
 * @see \Illuminate\Contracts\Auth\StatefulGuard
 */
class FiberAuth extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'fiberflow.auth';
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
     * Set the authenticated user for the current Fiber.
     */
    public static function setFiberUser(\Illuminate\Contracts\Auth\Authenticatable $user): void
    {
        if (\Fiber::getCurrent() !== null) {
            FiberContext::set('auth.user', $user);
            static::guard()->setUser($user);
        }
    }

    /**
     * Get the authenticated user for the current Fiber.
     */
    public static function getFiberUser(): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        if (\Fiber::getCurrent() !== null && FiberContext::has('auth.user')) {
            return FiberContext::get('auth.user');
        }

        return static::user();
    }

    /**
     * Check if the current Fiber has an authenticated user.
     */
    public static function fiberCheck(): bool
    {
        return static::getFiberUser() !== null;
    }

    /**
     * Clear authentication state for the current Fiber.
     */
    public static function clearFiberAuth(): void
    {
        if (\Fiber::getCurrent() !== null) {
            FiberContext::forget('auth.user');
            static::logout();
        }
    }
}

<?php

declare(strict_types=1);

namespace FiberFlow\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \FiberFlow\Http\AsyncHttpResponse get(string $url, array $headers = [])
 * @method static \FiberFlow\Http\AsyncHttpResponse post(string $url, array $data = [], array $headers = [])
 * @method static \FiberFlow\Http\AsyncHttpResponse put(string $url, array $data = [], array $headers = [])
 * @method static \FiberFlow\Http\AsyncHttpResponse patch(string $url, array $data = [], array $headers = [])
 * @method static \FiberFlow\Http\AsyncHttpResponse delete(string $url, array $headers = [])
 * @method static \FiberFlow\Http\AsyncHttpResponse request(string $method, string $url, array $options = [])
 *
 * @see \FiberFlow\Http\AsyncHttpClient
 */
class AsyncHttp extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'fiberflow.http';
    }
}

<?php

declare(strict_types=1);

namespace FiberFlow;

use FiberFlow\Console\FiberWorkCommand;
use FiberFlow\Coroutine\SandboxManager;
use FiberFlow\Database\AsyncDbConnection;
use FiberFlow\Facades\AsyncHttp;
use FiberFlow\Loop\ConcurrencyManager;
use FiberFlow\Loop\FiberLoop;
use FiberFlow\Metrics\MetricsCollector;
use Illuminate\Support\ServiceProvider;

class FiberFlowServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/fiberflow.php',
            'fiberflow'
        );

        $this->registerCoreServices();
        $this->registerFacades();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/fiberflow.php' => config_path('fiberflow.php'),
            ], 'fiberflow-config');

            $this->commands([
                FiberWorkCommand::class,
            ]);
        }
    }

    /**
     * Register core FiberFlow services.
     */
    protected function registerCoreServices(): void
    {
        $this->app->singleton(SandboxManager::class, function ($app) {
            return new SandboxManager($app);
        });

        $this->app->singleton(ConcurrencyManager::class, function ($app) {
            return new ConcurrencyManager(
                maxConcurrency: config('fiberflow.max_concurrency', 50)
            );
        });

        $this->app->singleton(FiberLoop::class, function ($app) {
            return new FiberLoop(
                $app->make(ConcurrencyManager::class),
                $app->make(SandboxManager::class)
            );
        });

        $this->app->singleton(MetricsCollector::class, function ($app) {
            return new MetricsCollector();
        });
    }

    /**
     * Register facade bindings.
     */
    protected function registerFacades(): void
    {
        $this->app->singleton('fiberflow.http', function ($app) {
            return new \FiberFlow\Http\AsyncHttpClient(
                timeout: config('fiberflow.http.timeout', 30),
                retryAttempts: config('fiberflow.http.retry_attempts', 3),
                retryDelay: config('fiberflow.http.retry_delay', 1000)
            );
        });

        // Fiber-aware Auth facade
        $this->app->singleton('fiberflow.auth', function ($app) {
            return $app->make('auth');
        });

        // Fiber-aware Cache facade
        $this->app->singleton('fiberflow.cache', function ($app) {
            return $app->make('cache');
        });

        // Fiber-aware Session facade
        $this->app->singleton('fiberflow.session', function ($app) {
            return $app->make('session');
        });

        // Async Database connection
        $this->app->singleton(AsyncDbConnection::class, function ($app) {
            return new AsyncDbConnection(config('database.connections.mysql', []));
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            SandboxManager::class,
            ConcurrencyManager::class,
            FiberLoop::class,
            'fiberflow.http',
            'fiberflow.auth',
            'fiberflow.cache',
            'fiberflow.session',
        ];
    }
}


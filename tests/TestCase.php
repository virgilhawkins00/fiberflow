<?php

declare(strict_types=1);

namespace FiberFlow\Tests;

use FiberFlow\FiberFlowServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * The last response from the application.
     *
     * @var \Illuminate\Testing\TestResponse|null
     */
    protected static $latestResponse;

    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup for tests
    }

    protected function getPackageProviders($app): array
    {
        return [
            FiberFlowServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'AsyncHttp' => \FiberFlow\Facades\AsyncHttp::class,
            'AsyncDb' => \FiberFlow\Facades\AsyncDb::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('queue.default', 'redis');

        // Load FiberFlow configuration
        config()->set('fiberflow', require __DIR__.'/../config/fiberflow.php');
    }
}

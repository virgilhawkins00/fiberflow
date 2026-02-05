<?php

declare(strict_types=1);

namespace Tests\Integration;

use FiberFlow\FiberFlowServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case for integration tests that require Laravel application context.
 */
abstract class IntegrationTestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure database for testing
        $this->setUpDatabase();
    }

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [
            FiberFlowServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     */
    protected function defineEnvironment($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup MySQL connection for integration tests
        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3307),
            'database' => env('DB_DATABASE', 'fiberflow_test'),
            'username' => env('DB_USERNAME', 'fiberflow'),
            'password' => env('DB_PASSWORD', 'fiberflow'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);

        // Setup RabbitMQ connection for integration tests
        $app['config']->set('queue.connections.rabbitmq', [
            'driver' => 'rabbitmq',
            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => env('RABBITMQ_PORT', 5673),
            'user' => env('RABBITMQ_USER', 'fiberflow'),
            'password' => env('RABBITMQ_PASSWORD', 'fiberflow'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
            'queue' => env('RABBITMQ_QUEUE', 'fiberflow_test'),
        ]);

        // Setup FiberFlow configuration
        $app['config']->set('fiberflow.concurrency.max', 10);
        $app['config']->set('fiberflow.concurrency.timeout', 30);
        $app['config']->set('fiberflow.database.enabled', true);
        $app['config']->set('fiberflow.database.pool_size', 5);
        $app['config']->set('fiberflow.http.timeout', 30);
        $app['config']->set('fiberflow.http.retry_attempts', 3);
        $app['config']->set('fiberflow.http.retry_delay', 100);
    }

    /**
     * Setup the database for testing.
     */
    protected function setUpDatabase(): void
    {
        // Create jobs table for queue testing
        $this->app['db']->connection('testing')->getSchemaBuilder()->create('jobs', function ($table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        // Create failed_jobs table
        $this->app['db']->connection('testing')->getSchemaBuilder()->create('failed_jobs', function ($table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    /**
     * Check if MySQL is available for integration tests.
     */
    protected function isMySqlAvailable(): bool
    {
        try {
            $connection = new \PDO(
                sprintf(
                    'mysql:host=%s;port=%s;dbname=%s',
                    config('database.connections.mysql.host'),
                    config('database.connections.mysql.port'),
                    config('database.connections.mysql.database'),
                ),
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
            );
            $connection = null;

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if RabbitMQ is available for integration tests.
     */
    protected function isRabbitMqAvailable(): bool
    {
        try {
            // Use Ridge library to check RabbitMQ availability
            $client = \PHPinnacle\Ridge\Client::create('amqp://fiberflow:fiberflow@127.0.0.1:5673/');
            $client->connect();
            $client->disconnect();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

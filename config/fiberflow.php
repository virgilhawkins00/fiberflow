<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Concurrency
    |--------------------------------------------------------------------------
    |
    | The maximum number of Fibers that can run concurrently in a single
    | worker process. Higher values allow more jobs to run simultaneously
    | but consume more memory. Recommended: 50-100 for I/O-heavy workloads.
    |
    */

    'max_concurrency' => env('FIBERFLOW_MAX_CONCURRENCY', 50),

    /*
    |--------------------------------------------------------------------------
    | Memory Limit
    |--------------------------------------------------------------------------
    |
    | Maximum memory (in MB) that a single worker process can consume.
    | The worker will gracefully restart when approaching this limit.
    | Set to 0 to disable memory limit checks.
    |
    */

    'memory_limit' => env('FIBERFLOW_MEMORY_LIMIT', 256),

    /*
    |--------------------------------------------------------------------------
    | Job Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time (in seconds) a single job can run before being terminated.
    | This prevents runaway jobs from blocking the worker indefinitely.
    | Set to 0 to disable timeout.
    |
    */

    'timeout' => env('FIBERFLOW_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Sleep Duration
    |--------------------------------------------------------------------------
    |
    | Time (in seconds) to sleep when no jobs are available. Lower values
    | provide faster job pickup but increase CPU usage. Recommended: 1-3.
    |
    */

    'sleep' => env('FIBERFLOW_SLEEP', 1),

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | Enable the TUI (Terminal User Interface) dashboard for real-time
    | monitoring of Fiber activity, memory usage, and job throughput.
    | Can be overridden with --dashboard flag.
    |
    */

    'dashboard' => env('FIBERFLOW_DASHBOARD', false),

    /*
    |--------------------------------------------------------------------------
    | Container Sandboxing
    |--------------------------------------------------------------------------
    |
    | Enable container isolation for each Fiber to prevent state pollution
    | between concurrent jobs. Highly recommended for multi-tenant apps.
    | Disabling this may improve performance but risks data leakage.
    |
    */

    'sandbox_enabled' => env('FIBERFLOW_SANDBOX_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Async HTTP Client
    |--------------------------------------------------------------------------
    |
    | Configuration for the AsyncHttp facade using amphp/http-client.
    |
    */

    'http' => [
        'timeout' => env('FIBERFLOW_HTTP_TIMEOUT', 30),
        'retry_attempts' => env('FIBERFLOW_HTTP_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('FIBERFLOW_HTTP_RETRY_DELAY', 1000), // milliseconds
        'max_redirects' => env('FIBERFLOW_HTTP_MAX_REDIRECTS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Async Database
    |--------------------------------------------------------------------------
    |
    | Configuration for async database operations (when available).
    | Currently experimental - use with caution in production.
    |
    */

    'database' => [
        'enabled' => env('FIBERFLOW_DB_ENABLED', false),
        'pool_size' => env('FIBERFLOW_DB_POOL_SIZE', 10),
        'timeout' => env('FIBERFLOW_DB_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Metrics
    |--------------------------------------------------------------------------
    |
    | Enable metrics collection for monitoring and observability.
    |
    */

    'metrics' => [
        'enabled' => env('FIBERFLOW_METRICS_ENABLED', true),
        'interval' => env('FIBERFLOW_METRICS_INTERVAL', 60), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | Configure how FiberFlow handles errors and exceptions.
    |
    */

    'errors' => [
        'max_attempts' => env('FIBERFLOW_MAX_ATTEMPTS', 3),
        'backoff' => env('FIBERFLOW_BACKOFF', 'exponential'), // linear, exponential
        'report_to_sentry' => env('FIBERFLOW_REPORT_TO_SENTRY', false),
    ],

    'error_handling' => [
        'max_retries' => env('FIBERFLOW_MAX_RETRIES', 3),
        'retry_delay' => env('FIBERFLOW_RETRY_DELAY', 1), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Graceful Shutdown
    |--------------------------------------------------------------------------
    |
    | Configure graceful shutdown behavior.
    |
    */

    'shutdown_timeout' => env('FIBERFLOW_SHUTDOWN_TIMEOUT', 30), // seconds

    /*
    |--------------------------------------------------------------------------
    | Memory Leak Detection
    |--------------------------------------------------------------------------
    |
    | Configure memory leak detection.
    |
    */

    'memory_leak' => [
        'max_samples' => env('FIBERFLOW_MEMORY_LEAK_MAX_SAMPLES', 100),
        'threshold' => env('FIBERFLOW_MEMORY_LEAK_THRESHOLD', 10 * 1024 * 1024), // 10MB
        'sample_interval' => env('FIBERFLOW_MEMORY_LEAK_SAMPLE_INTERVAL', 5), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Mode
    |--------------------------------------------------------------------------
    |
    | Enable additional debugging features and verbose logging.
    | Should be disabled in production for performance.
    |
    */

    'debug' => env('FIBERFLOW_DEBUG', env('APP_DEBUG', false)),

];

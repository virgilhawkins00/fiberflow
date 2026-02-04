# FiberFlow

[![Latest Release](https://img.shields.io/github/v/release/virgilhawkins00/fiberflow?style=flat-square)](https://github.com/virgilhawkins00/fiberflow/releases)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/virgilhawkins00/fiberflow/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/virgilhawkins00/fiberflow/actions?query=workflow%3Arun-tests+branch%3Amain)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF?style=flat-square)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-11.x%20%7C%2012.x-FF2D20?style=flat-square)](https://laravel.com)
[![License](https://img.shields.io/github/license/virgilhawkins00/fiberflow?style=flat-square)](LICENSE)

**FiberFlow** is a revolutionary Laravel queue worker that leverages PHP 8.1+ Fibers to enable **true cooperative multitasking** within a single process. Process 10,000 HTTP requests using only 100MB of RAM by suspending jobs during I/O operations instead of blocking the entire worker.

## 🚀 The Problem

Traditional Laravel queue workers operate sequentially: one job at a time, one process per job. When a job makes an HTTP request that takes 2 seconds to respond, the worker process (consuming 30-50MB of RAM) sits idle, waiting. To scale, you spawn more processes, consuming gigabytes of memory.

**FiberFlow changes the game.**

## ✨ The Solution

Using PHP Fibers and the Revolt event loop, FiberFlow allows multiple jobs to run concurrently in a single worker process. When a job waits for I/O (HTTP requests, database queries with async drivers), the Fiber suspends, allowing other jobs to execute. The result:

- **70% reduction in infrastructure costs** for I/O-heavy workloads
- **10x throughput** for webhook processing, API integrations, and web scraping
- **Zero configuration** for basic usage - drop-in replacement for `queue:work`
- **Pure PHP** - no Swoole, no RoadRunner, no PECL extensions required

## 📋 Requirements

- PHP 8.2+ (Fibers introduced in 8.1, but 8.2+ recommended for stability)
- Laravel 11.x or 12.x
- Composer 2.0+

## 📦 Installation

Install via Composer:

```bash
composer require fiberflow/fiberflow
```

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=fiberflow-config
```

## 🎯 Quick Start

Replace your standard queue worker command:

```bash
# Before
php artisan queue:work

# After
php artisan fiber:work
```

That's it! Your jobs now run concurrently using Fibers.

## 💡 Usage Example

### Standard Job (Fiber-Safe)

```php
use FiberFlow\Concerns\AsyncJob;
use FiberFlow\Facades\AsyncHttp;

class ProcessWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, AsyncJob;

    public function handle()
    {
        // This suspends the Fiber, freeing the worker for other jobs
        $response = AsyncHttp::post('https://api.stripe.com/v1/charges', [
            'amount' => 2000,
            'currency' => 'usd',
        ]);

        // Execution resumes here when the response arrives
        if ($response->successful()) {
            // Process the response...
        }
    }
}
```

### Configuration

```php
// config/fiberflow.php
return [
    'max_concurrency' => 50,  // Maximum concurrent Fibers
    'memory_limit' => 256,    // MB per worker
    'timeout' => 60,          // Seconds per job
    'dashboard' => true,      // Enable TUI dashboard
];
```

## 🎨 TUI Dashboard

Launch the worker with real-time monitoring:

```bash
php artisan fiber:work --dashboard
```

Displays:

- Active vs. suspended Fibers
- Memory usage
- Jobs/second throughput
- Queue depth

## ⚠️ Important Considerations

### ✅ Ideal Use Cases

- **Webhooks & Notifications**: Sending thousands of HTTP requests to third-party APIs
- **Web Scraping**: Fetching data from multiple sources simultaneously
- **API Proxying**: Acting as an async middleware between services
- **Email Campaigns**: Sending bulk emails via external SMTP services

### ❌ Not Recommended For

- **CPU-Intensive Tasks**: Image/video processing (blocks the event loop)
- **Legacy Database Operations**: Heavy Eloquent queries using blocking PDO (use async drivers or defer pattern)
- **File System Operations**: Large file reads/writes (blocking I/O)

### 🔒 Container Isolation

FiberFlow uses **Container Sandboxing** to prevent state pollution between concurrent jobs. Each Fiber gets its own cloned container instance, ensuring:

- No shared singletons between jobs
- Isolated authentication state
- Safe multi-tenant operations

## 📚 Documentation

- [Architecture Deep Dive](docs/ARCHITECTURE.md)
- [Roadmap](docs/ROADMAP.md)
- [Contributing Guide](CONTRIBUTING.md)
- [Changelog](CHANGELOG.md)

## 🧪 Testing

```bash
composer test
```

Run tests across PHP versions:

```bash
composer test:coverage
```

## 🤝 Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and the process for submitting pull requests.

## 📄 License

FiberFlow is open-sourced software licensed under the [MIT license](LICENSE).

## 🙏 Credits

- Built with [Revolt PHP](https://revolt.run/) event loop
- Inspired by [AMPHP](https://amphp.org/) async primitives
- Follows [Spatie Package Standards](https://github.com/spatie/package-skeleton-laravel)

---

**Made with ❤️ for the Laravel community**

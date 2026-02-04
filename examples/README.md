# FiberFlow Examples

This directory contains example jobs demonstrating how to use FiberFlow in your Laravel application.

## WebhookJob

A simple example showing how to send webhooks using the `AsyncHttp` facade.

**Key Features:**
- Non-blocking HTTP POST requests
- Automatic retry logic
- Proper error handling
- Job tagging for monitoring

**Usage:**
```php
use App\Jobs\WebhookJob;

WebhookJob::dispatch(
    url: 'https://api.example.com/webhook',
    payload: [
        'event' => 'user.created',
        'user_id' => 123,
        'timestamp' => now()->toIso8601String(),
    ],
    retries: 3
);
```

**Performance:**
- Traditional worker: 1 webhook = 2 seconds
- FiberFlow: 50 webhooks = 2 seconds (50x throughput!)

## DataScrapingJob

An advanced example showing concurrent HTTP requests within a single job.

**Key Features:**
- Multiple concurrent HTTP GET requests
- Data parsing and caching
- Performance metrics logging
- Error handling per URL

**Usage:**
```php
use App\Jobs\DataScrapingJob;

DataScrapingJob::dispatch(
    urls: [
        'https://example.com/page1',
        'https://example.com/page2',
        'https://example.com/page3',
        // ... up to 50+ URLs
    ],
    cacheKey: 'scraped_data_'.date('Y-m-d')
);
```

**Performance:**
- Traditional approach: 10 URLs × 2s each = 20 seconds
- FiberFlow: 10 URLs concurrently = ~2 seconds (10x faster!)

## Running the Examples

### 1. Start the FiberFlow Worker

```bash
php artisan fiber:work --concurrency=50
```

### 2. Dispatch Jobs

```bash
php artisan tinker

>>> WebhookJob::dispatch('https://webhook.site/your-unique-url', ['test' => 'data']);
>>> DataScrapingJob::dispatch(['https://example.com'], 'test_scrape');
```

### 3. Monitor Performance

Watch the worker output to see jobs being processed concurrently:

```
[2026-02-04 19:00:00] Processing: App\Jobs\WebhookJob
[2026-02-04 19:00:00] Processing: App\Jobs\WebhookJob
[2026-02-04 19:00:00] Processing: App\Jobs\WebhookJob
[2026-02-04 19:00:02] Processed:  App\Jobs\WebhookJob (2.1s)
[2026-02-04 19:00:02] Processed:  App\Jobs\WebhookJob (2.0s)
[2026-02-04 19:00:02] Processed:  App\Jobs\WebhookJob (2.1s)
```

## Best Practices

### ✅ DO Use FiberFlow For:

- **Webhooks**: Sending notifications to external APIs
- **API Calls**: Fetching data from third-party services
- **Web Scraping**: Downloading content from multiple URLs
- **Email Sending**: SMTP operations (with async driver)
- **File Downloads**: Fetching remote files
- **Microservice Communication**: HTTP-based service calls

### ❌ DON'T Use FiberFlow For:

- **CPU-Intensive Tasks**: Image processing, video encoding
- **Blocking Operations**: Non-async database queries
- **Long-Running Calculations**: Complex algorithms
- **File System Operations**: Unless using async file I/O

## Creating Your Own Jobs

```php
<?php

namespace App\Jobs;

use FiberFlow\Facades\AsyncHttp;
use Illuminate\Contracts\Queue\ShouldQueue;

class MyAsyncJob implements ShouldQueue
{
    public function handle(): void
    {
        // Use AsyncHttp for non-blocking requests
        $response = AsyncHttp::get('https://api.example.com/data');
        
        if ($response->successful()) {
            $data = $response->json();
            // Process data...
        }
    }
}
```

## Troubleshooting

### Job Timeout

If jobs are timing out, increase the timeout:

```bash
php artisan fiber:work --timeout=120
```

### Memory Issues

Reduce concurrency or increase memory limit:

```bash
php artisan fiber:work --concurrency=25 --memory=256
```

### Debugging

Enable debug mode in `config/fiberflow.php`:

```php
'debug' => true,
```

## Learn More

- [Architecture Documentation](../docs/ARCHITECTURE.md)
- [Roadmap](../docs/ROADMAP.md)
- [Contributing Guide](../CONTRIBUTING.md)


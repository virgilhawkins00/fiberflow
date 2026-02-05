<?php

declare(strict_types=1);

namespace App\Jobs;

use FiberFlow\Facades\AsyncHttp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Example job that scrapes data from multiple URLs concurrently.
 *
 * This demonstrates the power of FiberFlow: multiple HTTP requests
 * can be made concurrently within a single job, dramatically reducing
 * total execution time.
 */
class DataScrapingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param array<int, string> $urls
     */
    public function __construct(
        public array $urls,
        public string $cacheKey,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting data scraping', [
            'urls' => count($this->urls),
            'cache_key' => $this->cacheKey,
        ]);

        $results = [];
        $startTime = microtime(true);

        // Make all HTTP requests concurrently
        // Each request will suspend its Fiber, allowing others to run
        foreach ($this->urls as $url) {
            try {
                $response = AsyncHttp::get($url, [
                    'User-Agent' => 'FiberFlow Scraper/1.0',
                ]);

                if ($response->successful()) {
                    $results[$url] = [
                        'status' => 'success',
                        'data' => $this->parseData($response->body()),
                        'size' => strlen($response->body()),
                    ];
                } else {
                    $results[$url] = [
                        'status' => 'failed',
                        'code' => $response->status(),
                    ];
                }
            } catch (\Throwable $e) {
                $results[$url] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        $duration = microtime(true) - $startTime;

        // Cache the results
        Cache::put($this->cacheKey, $results, now()->addHours(24));

        Log::info('Data scraping completed', [
            'urls' => count($this->urls),
            'successful' => count(array_filter($results, fn ($r) => $r['status'] === 'success')),
            'duration' => round($duration, 2).'s',
        ]);
    }

    /**
     * Parse the scraped data.
     *
     * @return array<string, mixed>
     */
    protected function parseData(string $html): array
    {
        // Simple example - in real use case, you'd use a proper HTML parser
        return [
            'title' => $this->extractTitle($html),
            'length' => strlen($html),
            'scraped_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Extract title from HTML.
     */
    protected function extractTitle(string $html): ?string
    {
        if (preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}

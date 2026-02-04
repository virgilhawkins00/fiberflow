<?php

declare(strict_types=1);

namespace App\Jobs;

use FiberFlow\Facades\AsyncHttp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Example job that sends a webhook using FiberFlow's async HTTP client.
 *
 * This job demonstrates how to use AsyncHttp facade for non-blocking
 * HTTP requests within a Fiber-based queue worker.
 */
class WebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $url,
        public array $payload,
        public int $retries = 3
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Sending webhook', [
            'url' => $this->url,
            'payload' => $this->payload,
        ]);

        try {
            // This looks synchronous but actually suspends the Fiber
            // while waiting for the HTTP response, allowing other jobs
            // to run concurrently in the same worker process
            $response = AsyncHttp::post($this->url, $this->payload, [
                'Content-Type' => 'application/json',
                'User-Agent' => 'FiberFlow/1.0',
            ]);

            if ($response->successful()) {
                Log::info('Webhook sent successfully', [
                    'url' => $this->url,
                    'status' => $response->status(),
                ]);
            } else {
                Log::warning('Webhook failed', [
                    'url' => $this->url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                // Retry the job if it failed
                if ($this->attempts() < $this->retries) {
                    $this->release(30); // Retry after 30 seconds
                }
            }
        } catch (\Throwable $e) {
            Log::error('Webhook exception', [
                'url' => $this->url,
                'exception' => $e->getMessage(),
            ]);

            // Retry on exception
            if ($this->attempts() < $this->retries) {
                $this->release(60); // Retry after 60 seconds
            } else {
                $this->fail($e);
            }
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['webhook', parse_url($this->url, PHP_URL_HOST)];
    }
}


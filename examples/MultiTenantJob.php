<?php

declare(strict_types=1);

namespace App\Jobs;

use FiberFlow\Facades\FiberAuth;
use FiberFlow\Facades\FiberCache;
use FiberFlow\Facades\FiberSession;
use FiberFlow\Facades\AsyncHttp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Multi-Tenant Job Example
 *
 * Demonstrates how FiberFlow maintains complete isolation
 * between different tenants processing jobs concurrently.
 *
 * This job simulates a multi-tenant SaaS application where
 * multiple customers' jobs run simultaneously without any
 * state leakage between them.
 */
class MultiTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $tenantId,
        public int $userId,
        public array $data
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Set tenant context in Fiber-local storage
        FiberCache::contextPut('tenant_id', $this->tenantId);
        FiberCache::contextPut('user_id', $this->userId);

        // Set session data for this tenant
        FiberSession::fiberPut('tenant_id', $this->tenantId);
        FiberSession::fiberPut('user_id', $this->userId);
        FiberSession::fiberPut('processing_started_at', now());

        // Simulate tenant-specific configuration
        $tenantConfig = $this->loadTenantConfig();
        FiberCache::contextPut('tenant_config', $tenantConfig);

        // Process tenant-specific data
        $this->processData();

        // Make API calls with tenant context
        $this->syncWithExternalService();

        // Verify isolation
        $this->verifyIsolation();

        // Clean up Fiber-local state
        FiberSession::clearFiberSession();
    }

    /**
     * Load tenant-specific configuration.
     */
    protected function loadTenantConfig(): array
    {
        // Simulate loading tenant config from cache/database
        return FiberCache::fiberRemember(
            "tenant.{$this->tenantId}.config",
            3600,
            function () {
                return [
                    'api_key' => "tenant_{$this->tenantId}_key",
                    'webhook_url' => "https://tenant{$this->tenantId}.example.com/webhook",
                    'settings' => [
                        'notifications_enabled' => true,
                        'auto_sync' => true,
                    ],
                ];
            }
        );
    }

    /**
     * Process tenant-specific data.
     */
    protected function processData(): void
    {
        $tenantId = FiberCache::contextGet('tenant_id');
        $userId = FiberCache::contextGet('user_id');

        // Simulate data processing
        foreach ($this->data as $item) {
            // Each item is processed with tenant context
            $this->processItem($item, $tenantId, $userId);
        }

        // Store processing results in Fiber-scoped cache
        FiberCache::fiberPut('processing_results', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'items_processed' => count($this->data),
            'completed_at' => now(),
        ]);
    }

    /**
     * Process a single item.
     */
    protected function processItem(array $item, int $tenantId, int $userId): void
    {
        // Simulate item processing with tenant context
        $result = [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'item_id' => $item['id'] ?? null,
            'processed' => true,
        ];

        // Store in Fiber-local session
        FiberSession::fiberFlash("item.{$item['id']}.result", $result);
    }

    /**
     * Sync with external service using tenant credentials.
     */
    protected function syncWithExternalService(): void
    {
        $config = FiberCache::contextGet('tenant_config');

        // Make async HTTP request with tenant-specific API key
        $response = AsyncHttp::post($config['webhook_url'], [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'data' => $this->data,
            'timestamp' => now()->toIso8601String(),
        ], [
            'Authorization' => "Bearer {$config['api_key']}",
            'X-Tenant-ID' => (string) $this->tenantId,
        ]);

        if ($response->successful()) {
            FiberCache::fiberPut('sync_status', 'success');
        } else {
            FiberCache::fiberPut('sync_status', 'failed');
            throw new \RuntimeException('External sync failed');
        }
    }

    /**
     * Verify that tenant isolation is maintained.
     */
    protected function verifyIsolation(): void
    {
        $tenantId = FiberCache::contextGet('tenant_id');
        $userId = FiberCache::contextGet('user_id');
        $sessionTenantId = FiberSession::fiberGet('tenant_id');
        $sessionUserId = FiberSession::fiberGet('user_id');

        // Verify that context hasn't been polluted
        if ($tenantId !== $this->tenantId) {
            throw new \RuntimeException('Tenant ID mismatch - container pollution detected!');
        }

        if ($userId !== $this->userId) {
            throw new \RuntimeException('User ID mismatch - container pollution detected!');
        }

        if ($sessionTenantId !== $this->tenantId) {
            throw new \RuntimeException('Session tenant ID mismatch - state leakage detected!');
        }

        if ($sessionUserId !== $this->userId) {
            throw new \RuntimeException('Session user ID mismatch - state leakage detected!');
        }

        // Log successful isolation verification
        \Log::info('Multi-tenant isolation verified', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'fiber_id' => spl_object_hash(\Fiber::getCurrent()),
        ]);
    }
}


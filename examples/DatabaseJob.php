<?php

declare(strict_types=1);

namespace FiberFlow\Examples;

use FiberFlow\Facades\AsyncDb;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Example job demonstrating async database operations.
 *
 * This job shows how to use the AsyncDb facade for non-blocking
 * database queries that suspend the Fiber during I/O operations.
 */
class DatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $userId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Example 1: Query builder
        $user = AsyncDb::table('users')
            ->where('id', $this->userId)
            ->first();

        if ($user === null) {
            $this->fail(new \RuntimeException("User {$this->userId} not found"));
            return;
        }

        // Example 2: Raw query
        $orders = AsyncDb::fetchAll(
            'SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 10',
            [$this->userId]
        );

        // Example 3: Insert
        $logId = AsyncDb::table('activity_logs')->insert([
            'user_id' => $this->userId,
            'action' => 'job_processed',
            'metadata' => json_encode(['orders_count' => count($orders)]),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Example 4: Update
        AsyncDb::table('users')
            ->where('id', $this->userId)
            ->update([
                'last_activity_at' => date('Y-m-d H:i:s'),
                'orders_count' => count($orders),
            ]);

        // Example 5: Complex query with joins (raw SQL)
        $stats = AsyncDb::fetchOne(
            'SELECT 
                u.id,
                u.name,
                COUNT(o.id) as total_orders,
                SUM(o.total) as total_spent
            FROM users u
            LEFT JOIN orders o ON o.user_id = u.id
            WHERE u.id = ?
            GROUP BY u.id, u.name',
            [$this->userId]
        );

        // Log the results
        logger()->info('Database job completed', [
            'user_id' => $this->userId,
            'user_name' => $user['name'] ?? 'Unknown',
            'orders_count' => count($orders),
            'log_id' => $logId,
            'stats' => $stats,
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['database', 'user:' . $this->userId];
    }
}


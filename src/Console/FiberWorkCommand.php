<?php

declare(strict_types=1);

namespace FiberFlow\Console;

use FiberFlow\Loop\FiberLoop;
use FiberFlow\Metrics\MetricsCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FiberWorkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fiber:work
                            {connection? : The name of the queue connection to work}
                            {--queue= : The names of the queues to work}
                            {--daemon : Run the worker in daemon mode (Deprecated)}
                            {--once : Only process the next job on the queue}
                            {--stop-when-empty : Stop when the queue is empty}
                            {--delay=0 : The number of seconds to delay failed jobs (Deprecated)}
                            {--backoff=0 : The number of seconds to wait before retrying a job that encountered an uncaught exception}
                            {--max-jobs=0 : The number of jobs to process before stopping}
                            {--max-time=0 : The maximum number of seconds the worker should run}
                            {--force : Force the worker to run even in maintenance mode}
                            {--memory=128 : The memory limit in megabytes}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--rest=0 : Number of seconds to rest between jobs}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=1 : Number of times to attempt a job before logging it failed}
                            {--concurrency=50 : Maximum number of concurrent Fibers}
                            {--dashboard : Enable the TUI dashboard}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start processing jobs on the queue using Fiber-based concurrency';

    /**
     * Execute the console command.
     */
    public function handle(FiberLoop $loop, MetricsCollector $metrics): int
    {
        $this->displayBanner();

        $connection = $this->argument('connection') ?? config('queue.default');
        $queue = $this->option('queue') ?? 'default';
        $dashboardEnabled = $this->option('dashboard');

        $this->info("Starting FiberFlow worker on [{$connection}] queue [{$queue}]");
        $this->info("Max concurrency: {$this->option('concurrency')}");
        $this->info("Memory limit: {$this->option('memory')}MB");

        if ($dashboardEnabled) {
            $this->info('Dashboard: ENABLED');
        }

        $this->newLine();

        // Start dashboard rendering if enabled
        if ($dashboardEnabled) {
            $this->startDashboard($metrics);
        }

        try {
            $loop->run(
                connection: $connection,
                queue: $queue,
                options: $this->gatherWorkerOptions(),
            );
        } catch (\Throwable $e) {
            $this->error("Worker failed: {$e->getMessage()}");
            Log::error('FiberFlow worker error', [
                'exception' => $e,
                'connection' => $connection,
                'queue' => $queue,
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Start the dashboard rendering loop.
     */
    protected function startDashboard(MetricsCollector $metrics): void
    {
        $renderer = new DashboardRenderer($metrics);

        // Register a periodic callback to update the dashboard
        \Revolt\EventLoop::repeat(1.0, function () use ($renderer) {
            echo $renderer->render();
        });
    }

    /**
     * Display the FiberFlow banner.
     */
    protected function displayBanner(): void
    {
        $this->newLine();
        $this->line('  _____ _ _               _____ _');
        $this->line(' |  ___(_) |__   ___ _ __|  ___| | _____      __');
        $this->line(' | |_  | | \'_ \ / _ \ \'__| |_  | |/ _ \ \ /\ / /');
        $this->line(' |  _| | | |_) |  __/ |  |  _| | | (_) \ V  V /');
        $this->line(' |_|   |_|_.__/ \___|_|  |_|   |_|\___/ \_/\_/');
        $this->newLine();
        $this->line(' <fg=cyan>Revolutionary Laravel Queue Worker with PHP Fibers</>');
        $this->line(' <fg=gray>Version 0.1.0-alpha | MIT License</>');
        $this->newLine();
    }

    /**
     * Gather all the worker options.
     *
     * @return array<string, mixed>
     */
    protected function gatherWorkerOptions(): array
    {
        return [
            'once' => $this->option('once'),
            'stop_when_empty' => $this->option('stop-when-empty'),
            'backoff' => $this->option('backoff'),
            'max_jobs' => $this->option('max-jobs'),
            'max_time' => $this->option('max-time'),
            'force' => $this->option('force'),
            'memory' => $this->option('memory'),
            'sleep' => $this->option('sleep'),
            'rest' => $this->option('rest'),
            'timeout' => $this->option('timeout'),
            'tries' => $this->option('tries'),
            'concurrency' => $this->option('concurrency'),
            'dashboard' => $this->option('dashboard'),
        ];
    }
}

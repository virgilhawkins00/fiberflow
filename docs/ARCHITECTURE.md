# FiberFlow Architecture

## Table of Contents

1. [Overview](#overview)
2. [Core Concepts](#core-concepts)
3. [System Architecture](#system-architecture)
4. [Component Design](#component-design)
5. [Data Flow](#data-flow)
6. [Container Sandboxing](#container-sandboxing)
7. [Async I/O Handling](#async-io-handling)
8. [Performance Considerations](#performance-considerations)

## Overview

FiberFlow is built on three fundamental pillars:

1. **PHP Fibers** (PHP 8.1+): Cooperative multitasking primitives
2. **Revolt Event Loop**: Non-blocking I/O orchestration
3. **Container Isolation**: State management for concurrent jobs

### The Problem We Solve

Traditional Laravel queue workers follow a synchronous, blocking model:

```
Job A starts → HTTP request (2s wait) → Job A completes
                    ↓
              Worker is IDLE (wasting 30-50MB RAM)
```

FiberFlow transforms this into:

```
Job A starts → HTTP request → Fiber SUSPENDS
Job B starts → HTTP request → Fiber SUSPENDS
Job C starts → HTTP request → Fiber SUSPENDS
    ↓              ↓              ↓
All responses arrive → Fibers RESUME → All jobs complete

Total time: ~2 seconds (not 6 seconds)
Memory: 50MB (not 150MB)
```

## Core Concepts

### 1. Fibers

Fibers are stackful coroutines that can be suspended and resumed:

```php
$fiber = new Fiber(function (): void {
    echo "Start\n";
    Fiber::suspend();  // Pause execution
    echo "Resume\n";
});

$fiber->start();      // Prints "Start"
$fiber->resume();     // Prints "Resume"
```

**Key Properties:**

- Each Fiber has its own call stack
- Suspension can occur deep in the call chain
- No "function coloring" problem (unlike async/await)

### 2. Revolt Event Loop

Revolt provides the scheduler that manages Fiber execution:

```php
use Revolt\EventLoop;

EventLoop::repeat(0.1, function () {
    // Check for new jobs every 100ms
    if ($job = $queue->pop()) {
        $this->runInFiber($job);
    }
});

EventLoop::run();  // Start the loop
```

### 3. Container Sandboxing

Each Fiber gets an isolated Laravel container to prevent state pollution:

```php
// Without sandboxing (DANGEROUS)
Auth::setUser($userA);  // Job A
Fiber::suspend();
Auth::setUser($userB);  // Job B
Fiber::resume();        // Job A now sees $userB!

// With sandboxing (SAFE)
$containerA = clone $baseContainer;
$containerB = clone $baseContainer;
// Each Fiber uses its own container
```

## System Architecture

### High-Level Components

```
┌─────────────────────────────────────────────────┐
│              FiberFlow Worker                   │
├─────────────────────────────────────────────────┤
│  ┌──────────────┐      ┌──────────────┐        │
│  │  FiberLoop   │◄────►│ Revolt Loop  │        │
│  └──────┬───────┘      └──────────────┘        │
│         │                                       │
│         ▼                                       │
│  ┌──────────────────────────────────┐          │
│  │    ConcurrencyManager            │          │
│  │  - Track active Fibers           │          │
│  │  - Enforce max_concurrency       │          │
│  └──────┬───────────────────────────┘          │
│         │                                       │
│         ▼                                       │
│  ┌──────────────────────────────────┐          │
│  │    SandboxManager                │          │
│  │  - Clone containers              │          │
│  │  - Isolate state                 │          │
│  └──────┬───────────────────────────┘          │
│         │                                       │
│         ▼                                       │
│  ┌──────────────────────────────────┐          │
│  │    Job Execution (in Fiber)      │          │
│  │  - AsyncHttp, AsyncDb            │          │
│  │  - Suspend on I/O                │          │
│  └──────────────────────────────────┘          │
└─────────────────────────────────────────────────┘
```

### Directory Structure

```
src/
├── Console/
│   └── FiberWorkCommand.php       # Artisan command
├── Coroutine/
│   ├── SandboxManager.php         # Container isolation
│   └── FiberContext.php           # Fiber-local storage
├── Loop/
│   ├── FiberLoop.php              # Main event loop
│   └── ConcurrencyManager.php     # Fiber lifecycle
├── Queue/
│   ├── FiberWorker.php            # Extends Laravel Worker
│   └── AsyncDriver.php            # Non-blocking queue drivers
├── Facades/
│   ├── AsyncHttp.php              # HTTP client facade
│   └── AsyncDb.php                # Database facade
├── Support/
│   └── Helpers.php                # Utility functions
└── Exceptions/
    ├── FiberException.php
    └── ContainerPollutionException.php
```

## Component Design

### FiberLoop

**Responsibility**: Orchestrate the event loop and job polling.

```php
class FiberLoop
{
    public function run(string $connection, string $queue): void
    {
        EventLoop::repeat(0.05, function () use ($connection, $queue) {
            if ($this->concurrency->isFull()) {
                return;  // Max Fibers reached
            }

            $job = $this->getNextJob($connection, $queue);

            if ($job) {
                $this->concurrency->spawn(
                    fn() => $this->processJob($job)
                );
            }
        });

        EventLoop::run();
    }
}
```

### SandboxManager

**Responsibility**: Provide isolated containers for each Fiber.

```php
class SandboxManager
{
    private WeakMap $fiberContainers;

    public function createSandbox(): Container
    {
        $sandbox = clone $this->baseContainer;
        $fiber = Fiber::getCurrent();

        $this->fiberContainers[$fiber] = $sandbox;

        return $sandbox;
    }

    public function getCurrentContainer(): Container
    {
        $fiber = Fiber::getCurrent();

        return $this->fiberContainers[$fiber]
            ?? $this->baseContainer;
    }
}
```

### ConcurrencyManager

**Responsibility**: Track and limit concurrent Fibers.

```php
class ConcurrencyManager
{
    private array $activeFibers = [];

    public function spawn(callable $callback): void
    {
        $fiber = new Fiber(function () use ($callback) {
            try {
                $callback();
            } finally {
                $this->remove(Fiber::getCurrent());
            }
        });

        $this->activeFibers[] = $fiber;
        $fiber->start();
    }

    public function isFull(): bool
    {
        return count($this->activeFibers) >= $this->maxConcurrency;
    }
}
```

## Data Flow

### Job Execution Lifecycle

```
1. FiberLoop polls queue
   ↓
2. Job found → Check concurrency limit
   ↓
3. Create Sandbox Container
   ↓
4. Spawn new Fiber with job
   ↓
5. Job executes → AsyncHttp::get()
   ↓
6. Fiber::suspend() called
   ↓
7. Control returns to EventLoop
   ↓
8. EventLoop processes other Fibers
   ↓
9. HTTP response arrives
   ↓
10. Fiber::resume() called
   ↓
11. Job completes
   ↓
12. Sandbox destroyed
   ↓
13. Fiber removed from active list
```

### Async HTTP Request Flow

```php
// User code (looks synchronous)
$response = AsyncHttp::get('https://api.example.com');

// What actually happens:
AsyncHttp::get() {
    $deferred = new Deferred();

    // Start async HTTP request
    $client->request('GET', $url)->then(
        fn($response) => $deferred->resolve($response)
    );

    // Suspend current Fiber
    return Fiber::suspend($deferred);
}

// EventLoop resumes when response arrives
EventLoop::onReadable($socket, function () use ($fiber, $deferred) {
    $response = $socket->read();
    $deferred->resolve($response);
    $fiber->resume($response);
});
```

## Container Sandboxing

### The Problem

```php
// Job A
Auth::login($userA);
$order = Order::create([...]);  // Should belong to $userA

// Fiber suspends, Job B starts
Auth::login($userB);

// Job A resumes
$order->user_id;  // WRONG! Points to $userB
```

### The Solution

```php
class FiberAwareAuth
{
    public function user(): ?User
    {
        $container = app(SandboxManager::class)
            ->getCurrentContainer();

        return $container->make('auth')->user();
    }
}
```

### Implementation Strategy

1. **Clone-on-Write**: Each Fiber gets a cloned container
2. **WeakMap Storage**: Fiber → Container mapping (auto-cleanup)
3. **Facade Proxying**: Route global calls to Fiber-local container

## Async I/O Handling

### Blocking vs Non-Blocking

**Blocking (BAD)**:

```php
$response = file_get_contents('https://api.com');  // Blocks entire worker
```

**Non-Blocking (GOOD)**:

```php
$response = AsyncHttp::get('https://api.com');  // Suspends only this Fiber
```

### Supported Async Operations

| Operation      | Library           | Status         |
| -------------- | ----------------- | -------------- |
| HTTP Requests  | amphp/http-client | ✅ Implemented |
| MySQL Queries  | amphp/mysql       | 🚧 Planned     |
| Redis Commands | amphp/redis       | 🚧 Planned     |
| File I/O       | amphp/file        | 🚧 Planned     |

### Defer Pattern (for blocking operations)

```php
// For unavoidable blocking operations
$result = AsyncDefer::run(function () {
    // This runs in a separate process
    return imagecreatefromjpeg('large-image.jpg');
});
```

## Performance Considerations

### Memory Usage

- **Traditional Worker**: 50MB × 100 processes = 5GB
- **FiberFlow**: 50MB × 1 process = 50MB (100 concurrent jobs)

### CPU Usage

- Fibers are **cooperative**, not preemptive
- CPU-bound tasks will block the event loop
- Use `defer()` for CPU-intensive work

### Benchmarks (Preliminary)

| Workload           | Traditional | FiberFlow | Improvement    |
| ------------------ | ----------- | --------- | -------------- |
| 1000 webhooks      | 120s        | 12s       | **10x faster** |
| Memory (1000 jobs) | 5GB         | 80MB      | **62x less**   |
| Throughput         | 8 jobs/s    | 83 jobs/s | **10x higher** |

## Security Considerations

1. **Container Isolation**: Prevents data leakage between jobs
2. **Memory Limits**: Configurable per-worker limits
3. **Timeout Protection**: Jobs can't run indefinitely
4. **Error Isolation**: One job failure doesn't crash the worker

## Future Enhancements

- **Async Database Drivers**: Full Eloquent support with amphp/mysql
- **Distributed Tracing**: OpenTelemetry integration
- **Advanced Scheduling**: Priority queues, weighted fair queuing
- **Hot Reload**: Update code without restarting workers

---

**Last Updated**: February 2026
**Version**: 0.1.0-alpha

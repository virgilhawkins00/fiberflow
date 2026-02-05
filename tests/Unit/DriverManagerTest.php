<?php

declare(strict_types=1);

use FiberFlow\Queue\Contracts\AsyncQueueDriver;
use FiberFlow\Queue\DriverManager;

beforeEach(function () {
    $this->manager = new DriverManager;
});

test('it registers default drivers', function () {
    expect($this->manager->hasDriver('database'))->toBeTrue();
    expect($this->manager->hasDriver('sqs'))->toBeTrue();
    expect($this->manager->hasDriver('rabbitmq'))->toBeTrue();
});

test('it gets driver names', function () {
    $names = $this->manager->getDriverNames();

    expect($names)->toContain('database');
    expect($names)->toContain('sqs');
    expect($names)->toContain('rabbitmq');
});

test('it registers custom driver', function () {
    $customDriver = new class implements AsyncQueueDriver
    {
        public function push(string $queue, string $payload, int $delay = 0): ?string
        {
            return null;
        }

        public function pop(string $queue): ?\Illuminate\Contracts\Queue\Job
        {
            return null;
        }

        public function delete(string $queue, string $jobId): void {}

        public function release(string $queue, string $jobId, int $delay = 0): void {}

        public function size(string $queue): int
        {
            return 0;
        }

        public function clear(string $queue): void {}

        public function getName(): string
        {
            return 'custom';
        }

        public function isAsync(): bool
        {
            return true;
        }

        public function close(): void {}
    };

    $this->manager->register('custom', get_class($customDriver));

    expect($this->manager->hasDriver('custom'))->toBeTrue();
});

test('it throws exception for unknown driver', function () {
    $this->manager->driver('unknown');
})->throws(\InvalidArgumentException::class);

test('it checks if driver exists', function () {
    expect($this->manager->hasDriver('database'))->toBeTrue();
    expect($this->manager->hasDriver('nonexistent'))->toBeFalse();
});

test('it closes all drivers', function () {
    // This test just ensures closeAll doesn't throw
    $this->manager->closeAll();

    expect(true)->toBeTrue();
});

test('it can get custom driver instance', function () {
    $customDriver = new class implements AsyncQueueDriver
    {
        public function push(string $queue, string $payload, int $delay = 0): ?string
        {
            return null;
        }

        public function pop(string $queue): ?\Illuminate\Contracts\Queue\Job
        {
            return null;
        }

        public function delete(string $queue, string $jobId): void {}

        public function release(string $queue, string $jobId, int $delay = 0): void {}

        public function size(string $queue): int
        {
            return 0;
        }

        public function clear(string $queue): void {}

        public function getName(): string
        {
            return 'test';
        }

        public function isAsync(): bool
        {
            return true;
        }

        public function close(): void {}
    };

    $this->manager->register('test', get_class($customDriver));
    $driver = $this->manager->driver('test');

    expect($driver)->toBeInstanceOf(AsyncQueueDriver::class);
});

test('it reuses driver instances with same config', function () {
    $customDriver = new class implements AsyncQueueDriver
    {
        public function push(string $queue, string $payload, int $delay = 0): ?string
        {
            return null;
        }

        public function pop(string $queue): ?\Illuminate\Contracts\Queue\Job
        {
            return null;
        }

        public function delete(string $queue, string $jobId): void {}

        public function release(string $queue, string $jobId, int $delay = 0): void {}

        public function size(string $queue): int
        {
            return 0;
        }

        public function clear(string $queue): void {}

        public function getName(): string
        {
            return 'test';
        }

        public function isAsync(): bool
        {
            return true;
        }

        public function close(): void {}
    };

    $this->manager->register('test', get_class($customDriver));
    $driver1 = $this->manager->driver('test', ['key' => 'value']);
    $driver2 = $this->manager->driver('test', ['key' => 'value']);

    expect($driver1)->toBe($driver2);
});

test('it creates different instances for different configs', function () {
    $customDriver = new class implements AsyncQueueDriver
    {
        public function push(string $queue, string $payload, int $delay = 0): ?string
        {
            return null;
        }

        public function pop(string $queue): ?\Illuminate\Contracts\Queue\Job
        {
            return null;
        }

        public function delete(string $queue, string $jobId): void {}

        public function release(string $queue, string $jobId, int $delay = 0): void {}

        public function size(string $queue): int
        {
            return 0;
        }

        public function clear(string $queue): void {}

        public function getName(): string
        {
            return 'test';
        }

        public function isAsync(): bool
        {
            return true;
        }

        public function close(): void {}
    };

    $this->manager->register('test', get_class($customDriver));
    $driver1 = $this->manager->driver('test', ['key' => 'value1']);
    $driver2 = $this->manager->driver('test', ['key' => 'value2']);

    expect($driver1)->not->toBe($driver2);
});

test('it can extend with custom driver creator', function () {
    $customDriver = new class implements AsyncQueueDriver
    {
        public function push(string $queue, string $payload, int $delay = 0): ?string
        {
            return 'custom-job-id';
        }

        public function pop(string $queue): ?\Illuminate\Contracts\Queue\Job
        {
            return null;
        }

        public function delete(string $queue, string $jobId): void {}

        public function release(string $queue, string $jobId, int $delay = 0): void {}

        public function size(string $queue): int
        {
            return 0;
        }

        public function clear(string $queue): void {}

        public function getName(): string
        {
            return 'extended';
        }

        public function isAsync(): bool
        {
            return true;
        }

        public function close(): void {}
    };

    $this->manager->extend('extended', fn () => $customDriver);

    expect($this->manager->hasDriver('extended'))->toBeTrue();
});

test('it clears instances on closeAll', function () {
    $customDriver = new class implements AsyncQueueDriver
    {
        public function push(string $queue, string $payload, int $delay = 0): ?string
        {
            return null;
        }

        public function pop(string $queue): ?\Illuminate\Contracts\Queue\Job
        {
            return null;
        }

        public function delete(string $queue, string $jobId): void {}

        public function release(string $queue, string $jobId, int $delay = 0): void {}

        public function size(string $queue): int
        {
            return 0;
        }

        public function clear(string $queue): void {}

        public function getName(): string
        {
            return 'test';
        }

        public function isAsync(): bool
        {
            return true;
        }

        public function close(): void {}
    };

    $this->manager->register('test', get_class($customDriver));

    // Create some driver instances
    $driver1 = $this->manager->driver('test', ['key' => 'value1']);
    $driver2 = $this->manager->driver('test', ['key' => 'value2']);

    $this->manager->closeAll();

    // After closeAll, getting the same driver should create a new instance
    $driver3 = $this->manager->driver('test', ['key' => 'value1']);
    expect($driver3)->toBeInstanceOf(AsyncQueueDriver::class);
    expect($driver3)->not->toBe($driver1);
});

test('it can register multiple custom drivers', function () {
    $this->manager->register('custom1', get_class(new class implements AsyncQueueDriver
    {
        public function push(string $queue, string $payload, int $delay = 0): ?string
        {
            return null;
        }

        public function pop(string $queue): ?\Illuminate\Contracts\Queue\Job
        {
            return null;
        }

        public function delete(string $queue, string $jobId): void {}

        public function release(string $queue, string $jobId, int $delay = 0): void {}

        public function size(string $queue): int
        {
            return 0;
        }

        public function clear(string $queue): void {}

        public function getName(): string
        {
            return 'custom1';
        }

        public function isAsync(): bool
        {
            return true;
        }

        public function close(): void {}
    }));

    $this->manager->register('custom2', get_class(new class implements AsyncQueueDriver
    {
        public function push(string $queue, string $payload, int $delay = 0): ?string
        {
            return null;
        }

        public function pop(string $queue): ?\Illuminate\Contracts\Queue\Job
        {
            return null;
        }

        public function delete(string $queue, string $jobId): void {}

        public function release(string $queue, string $jobId, int $delay = 0): void {}

        public function size(string $queue): int
        {
            return 0;
        }

        public function clear(string $queue): void {}

        public function getName(): string
        {
            return 'custom2';
        }

        public function isAsync(): bool
        {
            return true;
        }

        public function close(): void {}
    }));

    expect($this->manager->hasDriver('custom1'))->toBeTrue();
    expect($this->manager->hasDriver('custom2'))->toBeTrue();
    expect($this->manager->getDriverNames())->toContain('custom1');
    expect($this->manager->getDriverNames())->toContain('custom2');
});

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

<?php

declare(strict_types=1);

use FiberFlow\ErrorHandling\ErrorHandler;
use FiberFlow\Exceptions\FiberCrashException;
use FiberFlow\Metrics\MetricsCollector;

test('it registers default handlers', function () {
    $handler = new ErrorHandler;

    expect($handler->hasHandler(FiberCrashException::class))->toBeTrue();
    expect($handler->hasHandler(Throwable::class))->toBeTrue();
});

test('it can register custom handlers', function () {
    $handler = new ErrorHandler;
    $called = false;

    $handler->register(RuntimeException::class, function () use (&$called) {
        $called = true;
    });

    expect($handler->hasHandler(RuntimeException::class))->toBeTrue();

    $handler->handle(new RuntimeException('test'));
    expect($called)->toBeTrue();
});

test('it handles exceptions with specific handlers', function () {
    $handler = new ErrorHandler;
    $message = null;

    $handler->register(InvalidArgumentException::class, function ($e) use (&$message) {
        $message = $e->getMessage();
    });

    $handler->handle(new InvalidArgumentException('custom error'));
    expect($message)->toBe('custom error');
});

test('it falls back to parent class handlers', function () {
    // Create handler without default handlers to test inheritance
    $handler = new class extends \FiberFlow\ErrorHandling\ErrorHandler
    {
        public function __construct()
        {
            // Skip default handlers registration
        }
    };

    $exceptionHandled = null;

    $handler->register(Exception::class, function ($e) use (&$exceptionHandled) {
        $exceptionHandled = $e;
    });

    // RuntimeException extends Exception, so it should match
    $exception = new RuntimeException('test');
    $handler->handle($exception);

    expect($exceptionHandled)->not->toBeNull();
    expect($exceptionHandled)->toBeInstanceOf(RuntimeException::class);
});

test('it can wrap callables with error handling', function () {
    $handler = new ErrorHandler;
    $exceptionHandled = false;

    $handler->register(RuntimeException::class, function () use (&$exceptionHandled) {
        $exceptionHandled = true;
    });

    $result = $handler->wrap(function () {
        throw new RuntimeException('test');
    });

    expect($result)->toBeNull();
    expect($exceptionHandled)->toBeTrue();
});

test('it returns result from wrapped callable on success', function () {
    $handler = new ErrorHandler;

    $result = $handler->wrap(function () {
        return 42;
    });

    expect($result)->toBe(42);
});

test('it can unregister handlers', function () {
    $handler = new ErrorHandler;

    $handler->register(RuntimeException::class, fn () => null);
    expect($handler->hasHandler(RuntimeException::class))->toBeTrue();

    $handler->unregister(RuntimeException::class);
    expect($handler->hasHandler(RuntimeException::class))->toBeFalse();
});

test('it handles Fiber crashes', function () {
    $metrics = new MetricsCollector;
    $handler = new ErrorHandler($metrics);

    $fiber = new Fiber(fn () => null);
    $job = new stdClass;
    $exception = new RuntimeException('crash');

    $handler->handleFiberCrash($fiber, $job, $exception);

    expect($metrics->get('fibers', 'failed'))->toBe(1);
    expect($metrics->get('jobs', 'failed'))->toBe(1);
});

test('it gets all registered handlers', function () {
    $handler = new ErrorHandler;

    $handlers = $handler->getHandlers();

    expect($handlers)->toBeArray();
    expect($handlers)->toHaveKey(FiberCrashException::class);
    expect($handlers)->toHaveKey(Throwable::class);
});

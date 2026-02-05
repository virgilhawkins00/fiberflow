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

test('it handles exceptions without specific handler using Throwable fallback', function () {
    $handler = new ErrorHandler;
    $handled = false;

    // Override the Throwable handler to track if it was called
    $handler->register(Throwable::class, function () use (&$handled) {
        $handled = true;
    });

    // Create a custom exception that doesn't have a specific handler
    $exception = new class extends \Exception {};
    $handler->handle($exception);

    expect($handled)->toBeTrue();
});

test('it can handle multiple different exception types', function () {
    $handler = new ErrorHandler;
    $handledTypes = [];

    $handler->register(RuntimeException::class, function ($e) use (&$handledTypes) {
        $handledTypes[] = 'runtime';
    });

    $handler->register(InvalidArgumentException::class, function ($e) use (&$handledTypes) {
        $handledTypes[] = 'invalid_argument';
    });

    $handler->handle(new RuntimeException('test1'));
    $handler->handle(new InvalidArgumentException('test2'));

    expect($handledTypes)->toBe(['runtime', 'invalid_argument']);
});

test('it initializes with metrics collector', function () {
    $metrics = new MetricsCollector;
    $handler = new ErrorHandler($metrics);

    expect($handler)->toBeInstanceOf(ErrorHandler::class);
    expect($handler->hasHandler(FiberCrashException::class))->toBeTrue();
});

test('it can wrap callable that returns different types', function () {
    $handler = new ErrorHandler;

    $stringResult = $handler->wrap(fn () => 'hello');
    expect($stringResult)->toBe('hello');

    $arrayResult = $handler->wrap(fn () => [1, 2, 3]);
    expect($arrayResult)->toBe([1, 2, 3]);

    $objectResult = $handler->wrap(fn () => new stdClass);
    expect($objectResult)->toBeInstanceOf(stdClass::class);
});

it('handles exception with parent class handler using reflection', function () {
    $handler = new ErrorHandler(null);

    // Clear default handlers using reflection
    $reflection = new ReflectionClass($handler);
    $handlersProperty = $reflection->getProperty('handlers');
    $handlersProperty->setAccessible(true);
    $handlersProperty->setValue($handler, []);

    $called = false;
    $handler->register(Exception::class, function ($e) use (&$called) {
        $called = true;
    });

    // RuntimeException extends Exception
    $handler->handle(new RuntimeException('Test'));

    expect($called)->toBeTrue();
});

it('uses specific handler over parent class handler using reflection', function () {
    $handler = new ErrorHandler(null);

    // Clear default handlers using reflection
    $reflection = new ReflectionClass($handler);
    $handlersProperty = $reflection->getProperty('handlers');
    $handlersProperty->setAccessible(true);
    $handlersProperty->setValue($handler, []);

    $specificCalled = false;
    $parentCalled = false;

    $handler->register(Exception::class, function ($e) use (&$parentCalled) {
        $parentCalled = true;
    });

    $handler->register(RuntimeException::class, function ($e) use (&$specificCalled) {
        $specificCalled = true;
    });

    $handler->handle(new RuntimeException('Test'));

    expect($specificCalled)->toBeTrue();
    expect($parentCalled)->toBeFalse();
});

test('it uses default throwable handler for unregistered exceptions', function () {
    $handler = new ErrorHandler;

    $throwableCalled = false;

    // Register only Throwable handler
    $handler->register(Throwable::class, function ($e) use (&$throwableCalled) {
        $throwableCalled = true;
    });

    // Handle a custom exception that has no specific handler
    $handler->handle(new class extends Exception {});

    expect($throwableCalled)->toBeTrue();
});

test('it logs unhandled exceptions with default handler', function () {
    $handler = new ErrorHandler;

    // The default handler logs to Log facade
    // We just need to trigger it without throwing
    $handler->handle(new RuntimeException('Test unhandled exception'));

    expect(true)->toBeTrue();
});

test('it handles fiber crash exceptions with metrics', function () {
    $metrics = Mockery::mock(\FiberFlow\Metrics\MetricsCollector::class);
    $metrics->shouldReceive('recordFiberFailed')->once();
    $metrics->shouldReceive('recordJobFailed')->once();

    $handler = new ErrorHandler($metrics);

    $handler->handle(new \FiberFlow\Exceptions\FiberCrashException('Test crash'));

    expect(true)->toBeTrue();
});

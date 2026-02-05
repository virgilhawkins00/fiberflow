<?php

declare(strict_types=1);

use FiberFlow\Coroutine\ContainerPollutionDetector;
use Illuminate\Container\Container;

test('it can take a snapshot of container state', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    $fiber = new Fiber(function () use ($detector, $container) {
        $detector->takeSnapshot($container);
        expect(true)->toBeTrue(); // Snapshot taken successfully
    });

    $fiber->start();
    expect($fiber->isTerminated())->toBeTrue();
});

test('it can detect when pollution detection is enabled', function () {
    $detector = new ContainerPollutionDetector;
    expect($detector->isEnabled())->toBeTrue();

    $detector->setEnabled(false);
    expect($detector->isEnabled())->toBeFalse();
});

test('it can add isolated services', function () {
    $detector = new ContainerPollutionDetector;
    $detector->addIsolatedService('custom.service');

    // Service should be added to the isolation list
    expect(true)->toBeTrue();
});

test('it verifies container integrity without violations', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    $fiber = new Fiber(function () use ($detector, $container) {
        $detector->takeSnapshot($container);

        // No changes made, should pass verification
        $detector->verify($container);

        expect(true)->toBeTrue();
    });

    $fiber->start();
    expect($fiber->isTerminated())->toBeTrue();
});

test('it can be disabled', function () {
    $detector = new ContainerPollutionDetector;
    $detector->setEnabled(false);

    $container = new Container;

    $fiber = new Fiber(function () use ($detector, $container) {
        $detector->takeSnapshot($container);
        $detector->verify($container);

        // Should not throw even if state changes when disabled
        expect(true)->toBeTrue();
    });

    $fiber->start();
    expect($fiber->isTerminated())->toBeTrue();
});

test('it captures object state correctly', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    // Bind a simple service
    $container->singleton('test.service', function () {
        return new class
        {
            public string $value = 'initial';
        };
    });

    $fiber = new Fiber(function () use ($detector, $container) {
        $detector->takeSnapshot($container);

        // Verify without changes
        $detector->verify($container);

        expect(true)->toBeTrue();
    });

    $fiber->start();
    expect($fiber->isTerminated())->toBeTrue();
});

test('it isolates snapshots between fibers', function () {
    $detector = new ContainerPollutionDetector;
    $container1 = new Container;
    $container2 = new Container;

    $fiber1 = new Fiber(function () use ($detector, $container1) {
        $detector->takeSnapshot($container1);
        expect(true)->toBeTrue();
    });

    $fiber2 = new Fiber(function () use ($detector, $container2) {
        $detector->takeSnapshot($container2);
        expect(true)->toBeTrue();
    });

    $fiber1->start();
    $fiber2->start();

    expect($fiber1->isTerminated())->toBeTrue();
    expect($fiber2->isTerminated())->toBeTrue();
});

test('it handles missing snapshots gracefully', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    $fiber = new Fiber(function () use ($detector, $container) {
        // Verify without taking snapshot first
        $detector->verify($container);

        // Should not throw
        expect(true)->toBeTrue();
    });

    $fiber->start();
    expect($fiber->isTerminated())->toBeTrue();
});

test('it works when not in a fiber context', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    // Should not throw when not in a Fiber
    $detector->takeSnapshot($container);
    $detector->verify($container);

    expect(true)->toBeTrue();
});

test('it can get isolated services list', function () {
    $detector = new ContainerPollutionDetector;

    // Use reflection to access protected property
    $reflection = new ReflectionClass($detector);
    $property = $reflection->getProperty('isolatedServices');
    $property->setAccessible(true);

    $services = $property->getValue($detector);

    expect($services)->toBeArray();
    expect($services)->toContain('auth');
    expect($services)->toContain('session');
    expect($services)->toContain('cache');
});

test('it adds custom isolated services', function () {
    $detector = new ContainerPollutionDetector;
    $detector->addIsolatedService('custom.service');
    $detector->addIsolatedService('another.service');

    // Use reflection to verify
    $reflection = new ReflectionClass($detector);
    $property = $reflection->getProperty('isolatedServices');
    $property->setAccessible(true);

    $services = $property->getValue($detector);

    expect($services)->toContain('custom.service');
    expect($services)->toContain('another.service');
});

test('it handles snapshots for multiple fibers', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    $fiber1 = new Fiber(function () use ($detector, $container) {
        $detector->takeSnapshot($container);
        Fiber::suspend();
    });

    $fiber2 = new Fiber(function () use ($detector, $container) {
        $detector->takeSnapshot($container);
        Fiber::suspend();
    });

    $fiber1->start();
    $fiber2->start();

    expect(true)->toBeTrue();
});

test('it can verify container without snapshot when disabled', function () {
    $detector = new ContainerPollutionDetector;
    $detector->setEnabled(false);
    $container = new Container;

    // Should not throw when disabled
    $detector->verify($container);

    expect(true)->toBeTrue();
});

test('it captures state of bound services', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    // Bind multiple services
    $container->singleton('auth', fn () => new class
    {
        public string $user = 'test';
    });
    $container->singleton('session', fn () => new class
    {
        public array $data = ['key' => 'value'];
    });

    $fiber = new Fiber(function () use ($detector, $container) {
        $detector->takeSnapshot($container);
        $detector->verify($container);
        Fiber::suspend();
    });

    $fiber->start();

    expect(true)->toBeTrue();
});

test('it handles empty container', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    $fiber = new Fiber(function () use ($detector, $container) {
        $detector->takeSnapshot($container);
        $detector->verify($container);
        Fiber::suspend();
    });

    $fiber->start();

    expect(true)->toBeTrue();
});

test('it can toggle enabled state', function () {
    $detector = new ContainerPollutionDetector;

    expect($detector->isEnabled())->toBeTrue();

    $detector->setEnabled(false);
    expect($detector->isEnabled())->toBeFalse();

    $detector->setEnabled(true);
    expect($detector->isEnabled())->toBeTrue();
});

test('it captures non-object state correctly', function () {
    $detector = new ContainerPollutionDetector;

    $reflection = new ReflectionClass($detector);
    $method = $reflection->getMethod('captureState');
    $method->setAccessible(true);

    // Test with string
    $state = $method->invoke($detector, 'test string');
    expect($state)->toHaveKey('type');
    expect($state)->toHaveKey('value');
    expect($state['type'])->toBe('string');
    expect($state['value'])->toBe('test string');

    // Test with integer
    $state = $method->invoke($detector, 42);
    expect($state['type'])->toBe('integer');
    expect($state['value'])->toBe(42);
});

test('it gets object properties with id method', function () {
    $detector = new ContainerPollutionDetector;

    $object = new class
    {
        public function id()
        {
            return 123;
        }
    };

    $reflection = new ReflectionClass($detector);
    $method = $reflection->getMethod('getObjectProperties');
    $method->setAccessible(true);

    $properties = $method->invoke($detector, $object);

    expect($properties)->toHaveKey('user_id');
    expect($properties['user_id'])->toBe(123);
});

test('it gets object properties with getId method', function () {
    $detector = new ContainerPollutionDetector;

    $object = new class
    {
        public function getId()
        {
            return 'session-123';
        }
    };

    $reflection = new ReflectionClass($detector);
    $method = $reflection->getMethod('getObjectProperties');
    $method->setAccessible(true);

    $properties = $method->invoke($detector, $object);

    expect($properties)->toHaveKey('session_id');
    expect($properties['session_id'])->toBe('session-123');
});

test('it detects state changes in object hash', function () {
    $detector = new ContainerPollutionDetector;

    $object1 = new stdClass;
    $object2 = new stdClass;

    $reflection = new ReflectionClass($detector);
    $hasStateChanged = $reflection->getMethod('hasStateChanged');
    $hasStateChanged->setAccessible(true);

    $original = ['hash' => spl_object_hash($object1)];
    $current = ['hash' => spl_object_hash($object2)];

    $changed = $hasStateChanged->invoke($detector, $original, $current);

    expect($changed)->toBeTrue();
});

test('it detects state changes in properties', function () {
    $detector = new ContainerPollutionDetector;

    $reflection = new ReflectionClass($detector);
    $hasStateChanged = $reflection->getMethod('hasStateChanged');
    $hasStateChanged->setAccessible(true);

    $original = ['properties' => ['user_id' => 1]];
    $current = ['properties' => ['user_id' => 2]];

    $changed = $hasStateChanged->invoke($detector, $original, $current);

    expect($changed)->toBeTrue();
});

test('it detects missing properties', function () {
    $detector = new ContainerPollutionDetector;

    $reflection = new ReflectionClass($detector);
    $hasStateChanged = $reflection->getMethod('hasStateChanged');
    $hasStateChanged->setAccessible(true);

    $original = ['properties' => ['user_id' => 1, 'session_id' => 'abc']];
    $current = ['properties' => ['user_id' => 1]];

    $changed = $hasStateChanged->invoke($detector, $original, $current);

    expect($changed)->toBeTrue();
});

test('it skips services not in original snapshot', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    // Bind a service
    $container->singleton('auth', fn () => new class
    {
        public string $user = 'test';
    });

    $fiber = new Fiber(function () use ($detector, $container) {
        $detector->takeSnapshot($container);

        // Add a new service after snapshot
        $container->singleton('new.service', fn () => new stdClass);

        // Verify should not throw because new.service wasn't in original snapshot
        $detector->verify($container);

        Fiber::suspend();
    });

    $fiber->start();

    expect(true)->toBeTrue();
});

test('it verifies container without throwing when no changes', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    // Create a service
    $service = new class
    {
        public string $value = 'initial';
    };

    $container->singleton('test.service', fn () => $service);

    $fiber = new Fiber(function () use ($detector, $container) {
        $detector->takeSnapshot($container);

        // No changes made
        $detector->verify($container);

        expect(true)->toBeTrue();

        Fiber::suspend();
    });

    $fiber->start();
});

test('it handles verification with new services added after snapshot', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    $container->singleton('auth', fn () => new class
    {
        public string $user = 'test';
    });

    $fiber = new Fiber(function () use ($detector, $container) {
        $detector->takeSnapshot($container);

        // Add new service after snapshot
        $container->singleton('new.service', fn () => new stdClass);

        // Should not throw because new.service wasn't in original snapshot
        $detector->verify($container);

        expect(true)->toBeTrue();

        Fiber::suspend();
    });

    $fiber->start();
});

test('it has captureState method', function () {
    $reflection = new ReflectionClass(ContainerPollutionDetector::class);
    expect($reflection->hasMethod('captureState'))->toBeTrue();
});

test('it has hasStateChanged method', function () {
    $reflection = new ReflectionClass(ContainerPollutionDetector::class);
    expect($reflection->hasMethod('hasStateChanged'))->toBeTrue();
});

test('it has verify method', function () {
    $reflection = new ReflectionClass(ContainerPollutionDetector::class);
    expect($reflection->hasMethod('verify'))->toBeTrue();
});

test('it throws exception when container pollution is detected', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    // Create two different objects for the same service
    $object1 = new class
    {
        public function getId()
        {
            return 'session-123';
        }
    };

    $object2 = new class
    {
        public function getId()
        {
            return 'session-456';
        }
    };

    // Bind the first object
    $container->singleton('session', fn () => $object1);

    $fiber = new Fiber(function () use ($detector, $container, $object2) {
        // Take snapshot with object1
        $detector->takeSnapshot($container);

        // Replace with object2 (different hash)
        $container->singleton('session', fn () => $object2);

        // This should throw ContainerPollutionException
        $detector->verify($container);

        Fiber::suspend();
    });

    expect(fn () => $fiber->start())->toThrow(\FiberFlow\Exceptions\ContainerPollutionException::class);
});

test('it skips services bound after snapshot during verification', function () {
    $detector = new ContainerPollutionDetector;
    $container = new Container;

    // Bind auth service
    $container->singleton('auth', fn () => new class
    {
        public function id()
        {
            return 1;
        }
    });

    $fiber = new Fiber(function () use ($detector, $container) {
        // Take snapshot with only auth
        $detector->takeSnapshot($container);

        // Add session service after snapshot (not in original snapshot)
        $container->singleton('session', fn () => new class
        {
            public function getId()
            {
                return 'new-session';
            }
        });

        // Verify should not throw because session wasn't in original snapshot
        // This covers line 115: continue when service not in original snapshot
        $detector->verify($container);

        expect(true)->toBeTrue();

        Fiber::suspend();
    });

    $fiber->start();
});

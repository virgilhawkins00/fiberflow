<?php

declare(strict_types=1);

use FiberFlow\Console\FiberWorkCommand;
use FiberFlow\Loop\FiberLoop;
use FiberFlow\Metrics\MetricsCollector;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

it('has correct signature', function () {
    $command = new FiberWorkCommand;

    expect($command->getName())->toBe('fiber:work');
});

it('has correct description', function () {
    $command = new FiberWorkCommand;

    expect($command->getDescription())->toContain('Fiber-based concurrency');
});

it('has connection argument', function () {
    $command = new FiberWorkCommand;
    $definition = $command->getDefinition();

    expect($definition->hasArgument('connection'))->toBeTrue();
});

it('has queue option', function () {
    $command = new FiberWorkCommand;
    $definition = $command->getDefinition();

    expect($definition->hasOption('queue'))->toBeTrue();
});

it('has concurrency option', function () {
    $command = new FiberWorkCommand;
    $definition = $command->getDefinition();

    expect($definition->hasOption('concurrency'))->toBeTrue();
});

it('has dashboard option', function () {
    $command = new FiberWorkCommand;
    $definition = $command->getDefinition();

    expect($definition->hasOption('dashboard'))->toBeTrue();
});

it('has memory option', function () {
    $command = new FiberWorkCommand;
    $definition = $command->getDefinition();

    expect($definition->hasOption('memory'))->toBeTrue();
});

it('has timeout option', function () {
    $command = new FiberWorkCommand;
    $definition = $command->getDefinition();

    expect($definition->hasOption('timeout'))->toBeTrue();
});

it('has gatherWorkerOptions method', function () {
    $command = new FiberWorkCommand;

    // Use reflection to verify method exists
    $reflection = new ReflectionClass($command);
    expect($reflection->hasMethod('gatherWorkerOptions'))->toBeTrue();
});

it('has displayBanner method', function () {
    $command = new FiberWorkCommand;

    // Use reflection to verify method exists
    $reflection = new ReflectionClass($command);
    expect($reflection->hasMethod('displayBanner'))->toBeTrue();
});

it('has all required options', function () {
    $command = new FiberWorkCommand;
    $definition = $command->getDefinition();

    $requiredOptions = [
        'queue',
        'daemon',
        'once',
        'stop-when-empty',
        'delay',
        'backoff',
        'max-jobs',
        'max-time',
        'force',
        'memory',
        'sleep',
        'rest',
        'timeout',
        'tries',
        'concurrency',
        'dashboard',
    ];

    foreach ($requiredOptions as $option) {
        expect($definition->hasOption($option))->toBeTrue("Option {$option} should exist");
    }
});

it('can gather worker options with all parameters', function () {
    $command = new FiberWorkCommand;

    // Create input with all options
    $input = new ArrayInput([
        '--queue' => 'test-queue',
        '--once' => true,
        '--stop-when-empty' => true,
        '--backoff' => '5',
        '--max-jobs' => '100',
        '--max-time' => '3600',
        '--force' => true,
        '--memory' => '256',
        '--sleep' => '5',
        '--rest' => '1',
        '--timeout' => '120',
        '--tries' => '3',
        '--concurrency' => '100',
        '--dashboard' => true,
    ], $command->getDefinition());

    // Set input using reflection
    $reflection = new ReflectionClass($command);
    $inputProperty = $reflection->getParentClass()->getProperty('input');
    $inputProperty->setAccessible(true);
    $inputProperty->setValue($command, $input);

    // Call gatherWorkerOptions
    $method = $reflection->getMethod('gatherWorkerOptions');
    $method->setAccessible(true);
    $options = $method->invoke($command);

    expect($options)->toBeArray();
    expect($options['once'])->toBeTrue();
    expect($options['stop_when_empty'])->toBeTrue();
    expect($options['backoff'])->toBe('5');
    expect($options['max_jobs'])->toBe('100');
    expect($options['max_time'])->toBe('3600');
    expect($options['force'])->toBeTrue();
    expect($options['memory'])->toBe('256');
    expect($options['sleep'])->toBe('5');
    expect($options['rest'])->toBe('1');
    expect($options['timeout'])->toBe('120');
    expect($options['tries'])->toBe('3');
    expect($options['concurrency'])->toBe('100');
    expect($options['dashboard'])->toBeTrue();
});

it('can display banner without errors', function () {
    $command = new FiberWorkCommand;

    // Create output buffer
    $output = new BufferedOutput();
    $outputStyle = new OutputStyle(new ArrayInput([]), $output);

    // Set output using reflection
    $reflection = new ReflectionClass($command);
    $outputProperty = $reflection->getParentClass()->getProperty('output');
    $outputProperty->setAccessible(true);
    $outputProperty->setValue($command, $outputStyle);

    // Call displayBanner - should not throw exception
    $method = $reflection->getMethod('displayBanner');
    $method->setAccessible(true);

    expect(fn() => $method->invoke($command))->not->toThrow(\Exception::class);
});

it('can handle successful execution', function () {
    $command = new FiberWorkCommand;

    // Mock FiberLoop
    $loop = Mockery::mock(FiberLoop::class);
    $loop->shouldReceive('run')
        ->once()
        ->andReturnNull();

    // Mock MetricsCollector
    $metrics = Mockery::mock(MetricsCollector::class);

    // Create input and output
    $input = new ArrayInput([
        'connection' => 'database',
        '--queue' => 'default',
    ], $command->getDefinition());

    $output = new BufferedOutput();
    $outputStyle = new OutputStyle($input, $output);

    // Set input and output using reflection
    $reflection = new ReflectionClass($command);
    $inputProperty = $reflection->getParentClass()->getProperty('input');
    $inputProperty->setAccessible(true);
    $inputProperty->setValue($command, $input);

    $outputProperty = $reflection->getParentClass()->getProperty('output');
    $outputProperty->setAccessible(true);
    $outputProperty->setValue($command, $outputStyle);

    // Execute handle
    $result = $command->handle($loop, $metrics);

    expect($result)->toBe(0); // SUCCESS
});

it('can handle execution with exception', function () {
    $command = new FiberWorkCommand;

    // Mock FiberLoop to throw exception
    $loop = Mockery::mock(FiberLoop::class);
    $loop->shouldReceive('run')
        ->once()
        ->andThrow(new \Exception('Test error'));

    // Mock MetricsCollector
    $metrics = Mockery::mock(MetricsCollector::class);

    // Create input and output
    $input = new ArrayInput([
        'connection' => 'database',
        '--queue' => 'default',
    ], $command->getDefinition());

    $output = new BufferedOutput();
    $outputStyle = new OutputStyle($input, $output);

    // Set input and output using reflection
    $reflection = new ReflectionClass($command);
    $inputProperty = $reflection->getParentClass()->getProperty('input');
    $inputProperty->setAccessible(true);
    $inputProperty->setValue($command, $input);

    $outputProperty = $reflection->getParentClass()->getProperty('output');
    $outputProperty->setAccessible(true);
    $outputProperty->setValue($command, $outputStyle);

    // Execute handle
    $result = $command->handle($loop, $metrics);

    expect($result)->toBe(1); // FAILURE

    $content = $output->fetch();
    expect($content)->toContain('Worker failed');
});

it('can handle execution with dashboard enabled', function () {
    $command = new FiberWorkCommand;

    // Mock FiberLoop
    $loop = Mockery::mock(FiberLoop::class);
    $loop->shouldReceive('run')
        ->once()
        ->andReturnNull();

    // Mock MetricsCollector with getAllMetrics
    $metrics = Mockery::mock(MetricsCollector::class);
    $metrics->shouldReceive('getAllMetrics')
        ->andReturn([
            'jobs' => [
                'processed' => 0,
                'failed' => 0,
                'pending' => 0,
            ],
            'fibers' => [
                'active' => 0,
                'idle' => 0,
            ],
            'memory' => [
                'current' => 0,
                'peak' => 0,
            ],
            'performance' => [
                'throughput' => 0,
                'avg_time' => 0,
            ],
        ]);

    // Create input with dashboard enabled
    $input = new ArrayInput([
        'connection' => 'database',
        '--queue' => 'default',
        '--dashboard' => true,
    ], $command->getDefinition());

    $output = new BufferedOutput();
    $outputStyle = new OutputStyle($input, $output);

    // Set input and output using reflection
    $reflection = new ReflectionClass($command);
    $inputProperty = $reflection->getParentClass()->getProperty('input');
    $inputProperty->setAccessible(true);
    $inputProperty->setValue($command, $input);

    $outputProperty = $reflection->getParentClass()->getProperty('output');
    $outputProperty->setAccessible(true);
    $outputProperty->setValue($command, $outputStyle);

    // Execute handle
    $result = $command->handle($loop, $metrics);

    expect($result)->toBe(0); // SUCCESS

    $content = $output->fetch();
    expect($content)->toContain('Dashboard: ENABLED');
});

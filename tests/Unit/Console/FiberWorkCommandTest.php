<?php

declare(strict_types=1);

use FiberFlow\Console\FiberWorkCommand;

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

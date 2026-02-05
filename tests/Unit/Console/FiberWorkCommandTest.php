<?php

declare(strict_types=1);

use FiberFlow\Console\FiberWorkCommand;

it('has correct signature', function () {
    $command = new FiberWorkCommand();
    
    expect($command->getName())->toBe('fiber:work');
});

it('has correct description', function () {
    $command = new FiberWorkCommand();
    
    expect($command->getDescription())->toContain('Fiber-based concurrency');
});

it('has connection argument', function () {
    $command = new FiberWorkCommand();
    $definition = $command->getDefinition();
    
    expect($definition->hasArgument('connection'))->toBeTrue();
});

it('has queue option', function () {
    $command = new FiberWorkCommand();
    $definition = $command->getDefinition();
    
    expect($definition->hasOption('queue'))->toBeTrue();
});

it('has concurrency option', function () {
    $command = new FiberWorkCommand();
    $definition = $command->getDefinition();
    
    expect($definition->hasOption('concurrency'))->toBeTrue();
});

it('has dashboard option', function () {
    $command = new FiberWorkCommand();
    $definition = $command->getDefinition();
    
    expect($definition->hasOption('dashboard'))->toBeTrue();
});

it('has memory option', function () {
    $command = new FiberWorkCommand();
    $definition = $command->getDefinition();
    
    expect($definition->hasOption('memory'))->toBeTrue();
});

it('has timeout option', function () {
    $command = new FiberWorkCommand();
    $definition = $command->getDefinition();
    
    expect($definition->hasOption('timeout'))->toBeTrue();
});


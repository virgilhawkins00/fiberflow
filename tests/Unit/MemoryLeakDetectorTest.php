<?php

declare(strict_types=1);

use FiberFlow\ErrorHandling\MemoryLeakDetector;

beforeEach(function () {
    $this->detector = new MemoryLeakDetector(100, 10 * 1024 * 1024, 0); // 0 second interval for testing
});

it('initializes with default configuration', function () {
    $detector = new MemoryLeakDetector();
    
    expect($detector)->toBeInstanceOf(MemoryLeakDetector::class);
});

it('takes memory samples', function () {
    $this->detector->sample();
    $samples = $this->detector->getSamples();
    
    expect($samples)->toHaveCount(1);
    expect($samples[0])->toBeInt();
    expect($samples[0])->toBeGreaterThan(0);
});

it('limits number of samples', function () {
    $detector = new MemoryLeakDetector(5, 10 * 1024 * 1024, 0);
    
    // Take 10 samples
    for ($i = 0; $i < 10; $i++) {
        $detector->sample();
    }
    
    $samples = $detector->getSamples();
    
    expect($samples)->toHaveCount(5); // Should keep only last 5
});

it('respects sample interval', function () {
    $detector = new MemoryLeakDetector(100, 10 * 1024 * 1024, 1); // 1 second interval
    
    $detector->sample();
    $detector->sample(); // Should be ignored (too soon)
    
    $samples = $detector->getSamples();
    
    expect($samples)->toHaveCount(1);
});

it('can reset samples', function () {
    $this->detector->sample();
    $this->detector->sample();
    
    $this->detector->reset();
    $samples = $this->detector->getSamples();
    
    expect($samples)->toBeEmpty();
});

it('can get current memory usage', function () {
    $current = $this->detector->getCurrentMemory();

    expect($current)->toBeInt();
    expect($current)->toBeGreaterThan(0);
});

it('can get peak memory usage', function () {
    $peak = $this->detector->getPeakMemory();

    expect($peak)->toBeInt();
    expect($peak)->toBeGreaterThan(0);
});


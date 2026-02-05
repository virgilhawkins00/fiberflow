<?php

declare(strict_types=1);

use FiberFlow\Console\DashboardRenderer;
use FiberFlow\Metrics\MetricsCollector;

beforeEach(function () {
    $this->metrics = new MetricsCollector;
    $this->renderer = new DashboardRenderer($this->metrics);
});

it('initializes with metrics collector', function () {
    expect($this->renderer)->toBeInstanceOf(DashboardRenderer::class);
});

it('can render dashboard', function () {
    $output = $this->renderer->render();

    expect($output)->toBeString();
    expect($output)->toContain('FiberFlow');
});

it('renders footer with controller help text', function () {
    $controller = Mockery::mock(\FiberFlow\Console\DashboardController::class);
    $controller->shouldReceive('getHelpText')
        ->once()
        ->andReturn('Test Help Text');

    $renderer = new DashboardRenderer($this->metrics, $controller);

    $reflection = new ReflectionClass($renderer);
    $method = $reflection->getMethod('renderFooter');
    $method->setAccessible(true);

    $footer = $method->invoke($renderer);

    expect($footer)->toContain('Test Help Text');
});

it('renders footer without controller', function () {
    $renderer = new DashboardRenderer($this->metrics);

    $reflection = new ReflectionClass($renderer);
    $method = $reflection->getMethod('renderFooter');
    $method->setAccessible(true);

    $footer = $method->invoke($renderer);

    expect($footer)->toContain('Press Ctrl+C to stop the worker');
});

it('formats duration with hours', function () {
    $reflection = new ReflectionClass($this->renderer);
    $method = $reflection->getMethod('formatDuration');
    $method->setAccessible(true);

    $result = $method->invoke($this->renderer, 7265); // 2h 1m 5s

    expect($result)->toBe('2h 1m 5s');
});

it('formats duration with minutes only', function () {
    $reflection = new ReflectionClass($this->renderer);
    $method = $reflection->getMethod('formatDuration');
    $method->setAccessible(true);

    $result = $method->invoke($this->renderer, 125); // 2m 5s

    expect($result)->toBe('2m 5s');
});

it('formats duration with seconds only', function () {
    $reflection = new ReflectionClass($this->renderer);
    $method = $reflection->getMethod('formatDuration');
    $method->setAccessible(true);

    $result = $method->invoke($this->renderer, 45); // 45s

    expect($result)->toBe('45s');
});

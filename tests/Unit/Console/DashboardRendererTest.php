<?php

declare(strict_types=1);

use FiberFlow\Console\DashboardRenderer;
use FiberFlow\Metrics\MetricsCollector;

beforeEach(function () {
    $this->metrics = new MetricsCollector();
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




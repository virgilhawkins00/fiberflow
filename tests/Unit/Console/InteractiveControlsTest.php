<?php

declare(strict_types=1);

use FiberFlow\Console\InteractiveControls;

beforeEach(function () {
    $this->controls = new InteractiveControls();
});

it('initializes with default state', function () {
    expect($this->controls)->toBeInstanceOf(InteractiveControls::class);
});




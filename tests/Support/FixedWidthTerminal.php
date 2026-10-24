<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Support;

use Runway\Console\Terminal\ITerminal;

class FixedWidthTerminal implements ITerminal {
    public int $calls = 0;

    public function __construct(
        public int $width = 80
    ) {}

    public function getWidth(): int {
        $this->calls++;

        return $this->width;
    }
}

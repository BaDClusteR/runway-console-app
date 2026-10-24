<?php

declare(strict_types=1);

namespace Runway\Console\Terminal;

interface ITerminal {
    /**
     * Width in columns; a default width if it cannot be detected (e.g. no terminal or no stty).
     */
    public function getWidth(): int;
}

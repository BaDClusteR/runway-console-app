<?php

declare(strict_types=1);

namespace Runway\Console\Output\Formatter;

interface IOutputFormatter {
    /**
     * Parse inline formatting tags and convert to ANSI escape codes.
     * Supports: <fg=color>, <bg=color>, <fg=color;bg=color>, </>
     */
    public function format(string $message): string;
}

<?php

declare(strict_types=1);

namespace Runway\Console\Terminal;

/**
 * The terminal size is read via stty, so it's always the current one (the terminal can be resized at any time).
 */
class Terminal implements ITerminal {
    protected const int DEFAULT_WIDTH = 80;

    protected const string DEVICE = '/dev/tty';

    public function getWidth(): int {
        $size = function_exists('shell_exec')
            // Errors are suppressed first: without a controlling terminal, even opening the device fails.
            ? trim((string)shell_exec('stty size 2>/dev/null < ' . static::DEVICE))
            : '';
        $columns = (int)(explode(' ', $size)[1] ?? 0);

        return ($columns > 0) ? $columns : static::DEFAULT_WIDTH;
    }
}

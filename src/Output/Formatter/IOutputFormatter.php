<?php

declare(strict_types=1);

namespace Runway\Console\Output\Formatter;

interface IOutputFormatter {
    /**
     * Parse inline formatting tags and convert to ANSI escape codes.
     * Supports: <fg=color>, <bg=color>, <fg=color;bg=color>, </>
     *
     * @param bool $isDecorated If false, the tags are removed instead (e.g. the output is not a terminal).
     */
    public function format(string $message, bool $isDecorated = true): string;

    /**
     * Escape arbitrary text (e.g. user data) so it's output as is: formatting tags are not parsed, and control
     * characters (except tabs and line breaks) are replaced, so the text cannot control the terminal.
     */
    public function escape(string $text): string;
}

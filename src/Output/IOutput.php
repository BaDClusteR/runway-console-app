<?php

declare(strict_types=1);

namespace Runway\Console\Output;

use Runway\Console\Output\Formatter\IOutputFormatter;
use Runway\Console\Output\ProgressBar\IProgressBar;

interface IOutput {
    public function write(string $message): void;

    public function writeln(string $message): void;

    public function success(string $message): void;

    public function error(string $message): void;

    public function warning(string $message): void;

    /**
     * Writes a line to STDERR.
     */
    public function writeErrorLine(string $message): void;

    public function info(string $message): void;

    /**
     * Escapes arbitrary text (e.g. user data), so it's output as is.
     */
    public function escape(string $text): string;

    /**
     * @param string[]   $headers
     * @param string[][] $rows
     */
    public function table(array $headers, array $rows): void;

    public function createProgressBar(int $max): IProgressBar;

    /**
     * A question with two buttons (see IPrompt::confirm()).
     */
    public function confirm(
        string $question,
        bool   $default = false,
        string $confirmLabel = 'Yes',
        string $cancelLabel = 'No'
    ): bool;

    /**
     * A list to choose from (see IPrompt::choice()).
     *
     * @param array<int|string, string> $choices Key => label.
     *
     * @return int|string The key of the chosen item.
     */
    public function choice(string $question, array $choices, int|string|null $default = null): int|string;

    /**
     * A text answer, asked again while the validator rejects it (see IPrompt::ask()).
     */
    public function ask(string $question, ?callable $validator = null, ?string $default = null): mixed;

    public function isInteractive(): bool;

    public function getFormatter(): IOutputFormatter;
}

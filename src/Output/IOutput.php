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

    public function info(string $message): void;

    /**
     * @param string[]   $headers
     * @param string[][] $rows
     */
    public function table(array $headers, array $rows): void;

    public function createProgressBar(int $max): IProgressBar;

    public function getFormatter(): IOutputFormatter;
}

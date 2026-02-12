<?php

declare(strict_types=1);

namespace Runway\Console\Output;

use Runway\Console\Output\Formatter\IOutputFormatter;
use Runway\Console\Output\ProgressBar\IProgressBar;
use Runway\Console\Output\ProgressBar\ProgressBar;
use Runway\Console\Output\Table\ITable;

class Output implements IOutput {
    public function __construct(
        protected IOutputFormatter $formatter,
        protected ITable           $table
    ) {}

    public function write(string $message): void {
        echo $this->formatter->format($message);
    }

    public function writeln(string $message): void {
        $this->write($message . PHP_EOL);
    }

    public function success(string $message): void {
        $this->writeln("<fg=black;bg=green> {$message} </>");
    }

    public function error(string $message): void {
        $this->writeln("<fg=white;bg=red> {$message} </>");
    }

    public function warning(string $message): void {
        $this->writeln("<fg=black;bg=yellow> {$message} </>");
    }

    public function info(string $message): void {
        $this->writeln("<fg=cyan>{$message}</>");
    }

    public function table(array $headers, array $rows): void {
        $this->write(
            $this->table->render($headers, $rows)
        );
    }

    public function createProgressBar(int $max): IProgressBar {
        return new ProgressBar($this->formatter, $max);
    }

    public function getFormatter(): IOutputFormatter {
        return $this->formatter;
    }
}

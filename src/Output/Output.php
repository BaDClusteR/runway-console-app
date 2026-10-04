<?php

declare(strict_types=1);

namespace Runway\Console\Output;

use Runway\Console\Output\Formatter\IOutputFormatter;
use Runway\Console\Output\ProgressBar\IProgressBar;
use Runway\Console\Output\ProgressBar\ProgressBar;
use Runway\Console\Output\Table\ITable;
use Runway\Console\Prompt\IPrompt;

class Output implements IOutput {
    protected bool $isDecorated;

    protected bool $isErrorDecorated;

    public function __construct(
        protected IOutputFormatter $formatter,
        protected ITable           $table,
        protected IPrompt          $prompt
    ) {
        $this->isDecorated = $this->isStreamDecorated(STDOUT);
        $this->isErrorDecorated = $this->isStreamDecorated(STDERR);
    }

    /**
     * Colors are used only for terminals, and can be disabled with NO_COLOR env variable (https://no-color.org).
     *
     * @param resource $stream
     */
    protected function isStreamDecorated($stream): bool {
        return (getenv('NO_COLOR') === false || getenv('NO_COLOR') === '')
            && stream_isatty($stream);
    }

    public function write(string $message): void {
        echo $this->formatter->format($message, $this->isDecorated);
    }

    public function writeln(string $message): void {
        $this->write($message . PHP_EOL);
    }

    public function success(string $message): void {
        $this->writeln("<fg=black;bg=green> {$message} </>");
    }

    public function error(string $message): void {
        $this->writeErrorLine("<fg=white;bg=red> {$message} </>");
    }

    public function warning(string $message): void {
        $this->writeErrorLine("<fg=black;bg=yellow> {$message} </>");
    }

    /**
     * Errors and warnings go to STDERR, so they are not mixed with the command output when it's redirected.
     */
    public function writeErrorLine(string $message): void {
        fwrite(STDERR, $this->formatter->format($message . PHP_EOL, $this->isErrorDecorated));
    }

    public function escape(string $text): string {
        return $this->formatter->escape($text);
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
        return new ProgressBar($this->formatter, $max, isDecorated: $this->isDecorated);
    }

    public function confirm(
        string $question,
        bool   $default = false,
        string $confirmLabel = 'Yes',
        string $cancelLabel = 'No'
    ): bool {
        return $this->prompt->confirm($question, $default, $confirmLabel, $cancelLabel);
    }

    public function choice(string $question, array $choices, int|string|null $default = null): int|string {
        return $this->prompt->choice($question, $choices, $default);
    }

    public function ask(string $question, ?callable $validator = null, ?string $default = null): mixed {
        return $this->prompt->ask($question, $validator, $default);
    }

    public function secret(string $question, ?callable $validator = null): mixed {
        return $this->prompt->secret($question, $validator);
    }

    public function isInteractive(): bool {
        return $this->prompt->isInteractive();
    }

    public function getFormatter(): IOutputFormatter {
        return $this->formatter;
    }
}

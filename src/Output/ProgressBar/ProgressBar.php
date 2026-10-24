<?php

declare(strict_types=1);

namespace Runway\Console\Output\ProgressBar;

use Runway\Console\Output\Formatter\IOutputFormatter;
use Runway\Console\Terminal\ITerminal;

class ProgressBar implements IProgressBar {
    /** Returns to the line start and clears the line: a shorter line must not leave the tail of the previous one. */
    protected const string REWRITE_LINE = "\r\033[K";

    /** The bar can be redrawn very often, so the terminal width is checked at most once per this many seconds. */
    protected const float TERMINAL_WIDTH_TTL = 1.0;

    protected int $current = 0;
    protected bool $isFinished = false;
    protected string $message = '';

    /** @var (callable(int $current, int $max): string)|null */
    protected $counterFormatter = null;

    protected ?int $terminalWidth = null;

    protected float $terminalWidthCheckedAt = 0.0;

    public function __construct(
        protected IOutputFormatter $formatter,
        protected ITerminal        $terminal,
        protected int              $max,
        protected int              $barWidth = 28,
        protected bool             $isDecorated = true
    ) {}

    public function start(): void {
        $this->current = 0;
        $this->isFinished = false;
        $this->display();
    }

    public function advance(int $step = 1): void {
        $this->setProgress($this->current + $step);
    }

    public function advancePercent(float $percent): void {
        $step = (int)round($this->max * $percent / 100);
        $this->setProgress($this->current + $step);
    }

    public function setProgress(int $current): void {
        $this->current = min(max($current, 0), $this->max);
        $this->display();
    }

    public function setProgressByPercent(float $percent): void {
        $percent = min(max($percent, 0.0), 100.0);
        $this->setProgress((int)round($this->max * $percent / 100));
    }

    public function finish(): void {
        $this->current = $this->max;
        $this->isFinished = true;

        // Without a terminal, only the final state is output.
        echo ($this->isDecorated ? static::REWRITE_LINE : '')
            . $this->formatter->format($this->buildLine(), $this->isDecorated)
            . PHP_EOL;
    }

    public function setMessage(string $message): void {
        $this->message = $message;
    }

    public function setCounterFormatter(?callable $formatter): void {
        $this->counterFormatter = $formatter;
    }

    protected function display(): void {
        // Without a terminal, intermediate states would just litter the output (see finish()).
        if ($this->isDecorated) {
            echo static::REWRITE_LINE . $this->formatter->format($this->buildLine());
        }
    }

    protected function buildLine(): string {
        // Nothing to do (max 0) is done only when finished.
        $fraction = ($this->max > 0)
            ? $this->current / $this->max
            : (float)$this->isFinished;

        $percent = (int)floor($fraction * 100);
        $filled = (int)round($this->barWidth * $fraction);

        $empty = $this->barWidth - $filled;

        $counter = $this->buildCounter();

        $bar = str_repeat('=', max(0, $filled - 1))
            . ($filled > 0 ? ($filled === $this->barWidth ? '=' : '>') : '')
            . str_repeat('-', $empty);

        $line = " {$counter} [<fg=green>{$bar}</>] {$percent}%";
        $message = ($this->message !== '') ? " {$this->message}" : '';

        // Without a terminal, the line is output once and never rewritten (see finish()).
        return $this->isDecorated ? $this->fitLine($line, $message) : $line . $message;
    }

    /**
     * A wrapped line cannot be rewritten (the cursor returns only to the start of its last part), so the line is cut
     * to the terminal width: the message first, so the bar keeps its formatting, and in a very narrow terminal the
     * whole line (without the formatting then).
     */
    protected function fitLine(string $line, string $message): string {
        // The last column is not used: the cursor there wraps the line in some terminals.
        $width = $this->getTerminalWidth() - 1;
        $lineWidth = $this->getVisibleWidth($line);

        if ($lineWidth + $this->getVisibleWidth($message) <= $width) {
            return $line . $message;
        }

        if ($lineWidth < $width) {
            return $line . $this->cut($message, $width - $lineWidth);
        }

        // No room for the message at all.
        return ($lineWidth === $width) ? $line : $this->cut($line, $width);
    }

    /**
     * Cuts the text to the width with "…" at the end. The formatting is dropped: the tags cannot be cut safely.
     */
    protected function cut(string $text, int $width): string {
        return $this->formatter->escape(
            mb_strimwidth($this->formatter->format($text, false), 0, max(0, $width), '…')
        );
    }

    protected function getVisibleWidth(string $text): int {
        return mb_strwidth($this->formatter->format($text, false));
    }

    protected function getTerminalWidth(): int {
        $now = $this->now();

        if ($this->terminalWidth === null || $now - $this->terminalWidthCheckedAt >= static::TERMINAL_WIDTH_TTL) {
            $this->terminalWidth = $this->terminal->getWidth();
            $this->terminalWidthCheckedAt = $now;
        }

        return $this->terminalWidth;
    }

    /**
     * Seconds, e.g. 1700000000.25.
     */
    protected function now(): float {
        return microtime(true);
    }

    protected function buildCounter(): string {
        if ($this->counterFormatter) {
            return ($this->counterFormatter)($this->current, $this->max);
        }

        $maxWidth = strlen((string)$this->max);

        return str_pad((string)$this->current, $maxWidth, ' ', STR_PAD_LEFT) . '/' . $this->max;
    }
}

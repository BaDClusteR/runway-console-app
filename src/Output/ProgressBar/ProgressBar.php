<?php

declare(strict_types=1);

namespace Runway\Console\Output\ProgressBar;

use Runway\Console\Output\Formatter\IOutputFormatter;

class ProgressBar implements IProgressBar {
    protected int $current = 0;
    protected string $message = '';

    /** @var (callable(int $current, int $max): string)|null */
    protected $counterFormatter = null;

    public function __construct(
        protected IOutputFormatter $formatter,
        protected int              $max,
        protected int              $barWidth = 28,
        protected bool             $isDecorated = true
    ) {}

    public function start(): void {
        $this->current = 0;
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
        $this->current = min($current, $this->max);
        $this->display();
    }

    public function setProgressByPercent(float $percent): void {
        $percent = min(max($percent, 0.0), 100.0);
        $this->setProgress((int)round($this->max * $percent / 100));
    }

    public function finish(): void {
        $this->current = $this->max;

        // Without a terminal, only the final state is output.
        echo ($this->isDecorated ? "\r" : "")
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
            echo "\r" . $this->formatter->format($this->buildLine());
        }
    }

    protected function buildLine(): string {
        $percent = $this->max > 0
            ? (int)floor($this->current / $this->max * 100)
            : 0;

        $filled = $this->max > 0
            ? (int)round($this->barWidth * $this->current / $this->max)
            : 0;

        $empty = $this->barWidth - $filled;

        $counter = $this->buildCounter();

        $bar = str_repeat('=', max(0, $filled - 1))
            . ($filled > 0 ? ($filled === $this->barWidth ? '=' : '>') : '')
            . str_repeat('-', $empty);

        $line = " {$counter} [<fg=green>{$bar}</>] {$percent}%";

        if ($this->message !== '') {
            $line .= " {$this->message}";
        }

        return $line;
    }

    protected function buildCounter(): string {
        if ($this->counterFormatter) {
            return ($this->counterFormatter)($this->current, $this->max);
        }

        $maxWidth = strlen((string)$this->max);

        return str_pad((string)$this->current, $maxWidth, ' ', STR_PAD_LEFT) . '/' . $this->max;
    }
}

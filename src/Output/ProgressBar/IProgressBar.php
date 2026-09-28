<?php

declare(strict_types=1);

namespace Runway\Console\Output\ProgressBar;

interface IProgressBar {
    public function start(): void;

    public function advance(int $step = 1): void;

    public function advancePercent(float $percent): void;

    public function setProgress(int $current): void;

    public function setProgressByPercent(float $percent): void;

    public function finish(): void;

    public function setMessage(string $message): void;

    /**
     * Sets how the counter ("12/345" by default) is rendered, e.g. to show sizes in human-readable units.
     *
     * @param (callable(int $current, int $max): string)|null $formatter Null restores the default counter.
     */
    public function setCounterFormatter(?callable $formatter): void;
}

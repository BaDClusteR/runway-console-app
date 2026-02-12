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
}

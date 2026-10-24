<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Unit\Output\ProgressBar;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Console\Output\Formatter\OutputFormatter;
use Runway\Console\Output\ProgressBar\ProgressBar;
use Runway\Console\Tests\Support\FixedWidthTerminal;

class ProgressBarTest extends TestCase {
    protected const string REWRITE = "\r\033[K";

    protected const string GREEN = "\033[32m";

    protected const string RESET = "\033[0m";

    protected FixedWidthTerminal $terminal;

    protected function setUp(): void {
        $this->terminal = new FixedWidthTerminal();
    }

    protected function createBar(int $max, int $barWidth = 10, bool $isDecorated = true): ProgressBar {
        return new ProgressBar(new OutputFormatter(), $this->terminal, $max, $barWidth, $isDecorated);
    }

    /**
     * @param callable(): void $action
     */
    protected function capture(callable $action): string {
        ob_start();

        try {
            $action();
        } finally {
            $output = (string)ob_get_clean();
        }

        return $output;
    }

    protected static function line(string $counter, string $bar, string $rest): string {
        return static::REWRITE . " {$counter} [" . static::GREEN . $bar . static::RESET . "] {$rest}";
    }

    public function testEveryRedrawRewritesTheLine(): void {
        $bar = $this->createBar(4);

        $output = $this->capture(static function () use ($bar): void {
            $bar->start();
            $bar->advance();
            $bar->advance(2);
            $bar->finish();
        });

        self::assertSame(
            static::line('0/4', '----------', '0%')
            . static::line('1/4', '==>-------', '25%')
            . static::line('3/4', '=======>--', '75%')
            . static::line('4/4', '==========', '100%') . PHP_EOL,
            $output
        );
    }

    public function testShorterMessageDoesNotLeaveTheTailOfThePreviousOne(): void {
        $bar = $this->createBar(2);

        $output = $this->capture(static function () use ($bar): void {
            $bar->setMessage('a long message');
            $bar->start();
            $bar->setMessage('short');
            $bar->advance();
        });

        self::assertSame(
            static::line('0/2', '----------', '0% a long message')
            . static::line('1/2', '====>-----', '50% short'),
            $output
        );
    }

    public function testWithoutTerminalOnlyTheFinalStateIsOutput(): void {
        $bar = $this->createBar(3, isDecorated: false);
        $bar->setMessage('<fg=red>Done</>');

        $output = $this->capture(static function () use ($bar): void {
            $bar->start();
            $bar->advance();
            $bar->finish();
        });

        self::assertSame(' 3/3 [==========] 100% Done' . PHP_EOL, $output);
        self::assertSame(0, $this->terminal->calls, 'The line is not fitted without a terminal.');
    }

    /**
     * @return iterable<string, array{int, int, string, string}>
     */
    public static function progressProvider(): iterable {
        yield 'empty' => [0, 100, '----------', '0%'];
        yield 'less than one cell' => [4, 100, '----------', '4%'];
        yield 'one cell' => [10, 100, '>---------', '10%'];
        yield 'rounded to the closest cell' => [15, 100, '=>--------', '15%'];
        yield 'percent is not rounded up' => [999, 1000, '==========', '99%'];
        yield 'almost full' => [94, 100, '========>-', '94%'];
        yield 'full' => [100, 100, '==========', '100%'];
    }

    #[DataProvider('progressProvider')]
    public function testBar(int $current, int $max, string $expectedBar, string $expectedPercent): void {
        $bar = $this->createBar($max, isDecorated: false);

        $output = $this->capture(static fn() => $bar->setProgress($current));

        self::assertSame('', $output);

        // The final state is the only output without a terminal, so the line is checked with a decorated bar.
        $bar = $this->createBar($max);
        $output = $this->capture(static fn() => $bar->setProgress($current));

        self::assertStringContainsString('[' . static::GREEN . $expectedBar . static::RESET . "] {$expectedPercent}", $output);
    }

    public function testProgressIsLimitedByMax(): void {
        $bar = $this->createBar(10);

        $output = $this->capture(static function () use ($bar): void {
            $bar->advance(25);
        });

        self::assertSame(static::line('10/10', '==========', '100%'), $output);
    }

    public function testProgressByPercent(): void {
        $bar = $this->createBar(200);

        $output = $this->capture(static function () use ($bar): void {
            $bar->setProgressByPercent(25);
            $bar->advancePercent(10);
            $bar->setProgressByPercent(150);
            $bar->setProgressByPercent(-5);
        });

        self::assertSame(
            static::line(' 50/200', '==>-------', '25%')
            . static::line(' 70/200', '===>------', '35%')
            . static::line('200/200', '==========', '100%')
            . static::line('  0/200', '----------', '0%'),
            $output
        );
    }

    public function testZeroMax(): void {
        $bar = $this->createBar(0);

        self::assertSame(static::line('0/0', '----------', '0%'), $this->capture($bar->start(...)));
    }

    public function testCounterFormatter(): void {
        $bar = $this->createBar(2048);
        $bar->setCounterFormatter(static fn(int $current, int $max): string => ($current / 1024) . ' of ' . ($max / 1024) . ' KB');

        $output = $this->capture(static fn() => $bar->setProgress(1024));
        self::assertSame(static::line('1 of 2 KB', '====>-----', '50%'), $output);

        $bar->setCounterFormatter(null);

        $output = $this->capture(static fn() => $bar->setProgress(1024));
        self::assertSame(static::line('1024/2048', '====>-----', '50%'), $output);
    }

    public function testLongMessageIsCutToTheTerminalWidth(): void {
        // " 1/2 [==========] 50%" takes 21 columns, the last terminal column is not used.
        $this->terminal->width = 32;
        $bar = $this->createBar(2);
        $bar->setMessage('Downloading <options=bold>file.zip</>');

        $output = $this->capture(static fn() => $bar->advance());

        // The bar keeps its formatting, the message is cut without it.
        self::assertSame(static::line('1/2', '====>-----', '50% Download…'), $output);
    }

    public function testMessageThatFitsIsNotCut(): void {
        $this->terminal->width = 43;
        $bar = $this->createBar(2);
        $bar->setMessage('Downloading <options=bold>file.zip</>');

        $output = $this->capture(static fn() => $bar->advance());

        self::assertSame(
            static::line('1/2', '====>-----', "50% Downloading \033[1mfile.zip" . static::RESET),
            $output
        );
    }

    public function testCutMessageIsNotParsedAsFormatting(): void {
        $this->terminal->width = 32;
        $bar = $this->createBar(2);
        $bar->setMessage('\\<fg=red>not a tag, a long text');

        $output = $this->capture(static fn() => $bar->advance());

        self::assertSame(static::line('1/2', '====>-----', '50% <fg=red>…'), $output);
    }

    public function testWholeLineIsCutInVeryNarrowTerminal(): void {
        $this->terminal->width = 12;
        $bar = $this->createBar(2);
        $bar->setMessage('message');

        $output = $this->capture(static fn() => $bar->advance());

        self::assertSame(static::REWRITE . ' 1/2 [====…', $output);
    }

    public function testWideCharactersAreCountedByColumns(): void {
        // 21 columns for the bar, a space, then 4 columns of the message fit.
        $this->terminal->width = 27;
        $bar = $this->createBar(2);
        $bar->setMessage('世界世界');

        $output = $this->capture(static fn() => $bar->advance());

        self::assertSame(static::line('1/2', '====>-----', '50% 世…'), $output);
    }

    public function testTerminalWidthIsNotCheckedOnEveryRedraw(): void {
        $bar = $this->createBar(1000);

        $this->capture(static function () use ($bar): void {
            for ($i = 0; $i < 1000; $i++) {
                $bar->advance();
            }
        });

        self::assertSame(1, $this->terminal->calls);
    }
}

<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Unit\Output;

use PHPUnit\Framework\TestCase;
use Runway\Console\Output\Formatter\OutputFormatter;
use Runway\Console\Output\Output;
use Runway\Console\Output\Table\Table;
use Runway\Console\Prompt\IPrompt;
use Runway\Console\Tests\Support\FixedWidthTerminal;
use Runway\Console\Tests\Support\StderrCapture;

class OutputTest extends TestCase {
    protected ?string $noColor = null;

    protected function setUp(): void {
        $noColor = getenv('NO_COLOR');
        $this->noColor = ($noColor === false) ? null : $noColor;
    }

    protected function tearDown(): void {
        putenv(($this->noColor === null) ? 'NO_COLOR' : "NO_COLOR={$this->noColor}");
    }

    /**
     * The decoration is normally detected by the streams, which depend on how the tests are run.
     */
    protected function createOutput(bool $isDecorated, ?IPrompt $prompt = null): Output {
        $formatter = new OutputFormatter();

        return new class($formatter, new Table($formatter), $prompt ?? $this->createStub(IPrompt::class), new FixedWidthTerminal(), $isDecorated) extends Output {
            public function __construct($formatter, $table, $prompt, $terminal, bool $isDecorated) {
                parent::__construct($formatter, $table, $prompt, $terminal);

                $this->isDecorated = $isDecorated;
                $this->isErrorDecorated = $isDecorated;
            }

            public function isDecorated(): bool {
                return $this->isDecorated;
            }
        };
    }

    /**
     * @param callable(): void $action
     *
     * @return array{string, string} STDOUT and STDERR.
     */
    protected function capture(callable $action): array {
        ob_start();
        StderrCapture::start();

        try {
            $action();
        } finally {
            $stderr = StderrCapture::stop();
            $stdout = (string)ob_get_clean();
        }

        return [$stdout, $stderr];
    }

    public function testWriteFormatsForTerminal(): void {
        $output = $this->createOutput(true);

        [$stdout, $stderr] = $this->capture(static function () use ($output): void {
            $output->write('<fg=green>a</>');
            $output->writeln(' b');
        });

        self::assertSame("\033[32ma\033[0m b" . PHP_EOL, $stdout);
        self::assertSame('', $stderr);
    }

    public function testWriteRemovesFormattingWithoutTerminal(): void {
        $output = $this->createOutput(false);

        [$stdout] = $this->capture(static function () use ($output): void {
            $output->writeln('<fg=green>a</> b');
            $output->success('Saved');
            $output->info('Note');
        });

        self::assertSame("a b\n Saved \nNote\n", $stdout);
    }

    public function testStyledMessages(): void {
        $output = $this->createOutput(true);

        [$stdout] = $this->capture(static function () use ($output): void {
            $output->success('Saved');
            $output->info('Note');
        });

        self::assertSame("\033[30;42m Saved \033[0m\n\033[36mNote\033[0m\n", $stdout);
    }

    public function testErrorsAndWarningsGoToStderr(): void {
        $output = $this->createOutput(false);

        [$stdout, $stderr] = $this->capture(static function () use ($output): void {
            $output->error('Failed');
            $output->warning('Careful');
            $output->writeErrorLine('<fg=red>Raw</>');
        });

        self::assertSame('', $stdout);
        self::assertSame(" Failed \n Careful \nRaw\n", $stderr);
    }

    public function testErrorIsStyledForTerminal(): void {
        $output = $this->createOutput(true);

        [, $stderr] = $this->capture(static fn() => $output->error('Failed'));

        self::assertSame("\033[37;41m Failed \033[0m\n", $stderr);
    }

    public function testEscape(): void {
        $output = $this->createOutput(false);

        [$stdout] = $this->capture(static fn() => $output->writeln($output->escape('<fg=red>x</>')));

        self::assertSame("<fg=red>x</>\n", $stdout);
    }

    public function testTable(): void {
        $output = $this->createOutput(false);

        [$stdout] = $this->capture(static fn() => $output->table(['A'], [['<fg=red>1</>']]));

        self::assertSame("┌───┐\n│ A │\n├───┤\n│ 1 │\n└───┘\n", $stdout);
    }

    public function testProgressBarFollowsDecoration(): void {
        $output = $this->createOutput(false);
        $bar = $output->createProgressBar(2);

        [$stdout] = $this->capture(static function () use ($bar): void {
            $bar->start();
            $bar->advance();
            $bar->finish();
        });

        // Without a terminal, only the final state.
        self::assertSame(' 2/2 [============================] 100%' . PHP_EOL, $stdout);
    }

    public function testPromptsAreDelegated(): void {
        $prompt = $this->createMock(IPrompt::class);
        $prompt->expects($this->once())->method('confirm')->with('Sure?', true, 'Do', 'Skip')->willReturn(true);
        $prompt->expects($this->once())->method('choice')->with('Pick', ['a' => 'A'], 'a')->willReturn('a');
        $prompt->expects($this->once())->method('ask')->with('Name?', null, 'Bob')->willReturn('Alice');
        $prompt->expects($this->once())->method('secret')->with('Password?', null)->willReturn('secret');
        $prompt->expects($this->once())->method('isInteractive')->willReturn(true);

        $output = $this->createOutput(false, $prompt);

        self::assertTrue($output->confirm('Sure?', true, 'Do', 'Skip'));
        self::assertSame('a', $output->choice('Pick', ['a' => 'A'], 'a'));
        self::assertSame('Alice', $output->ask('Name?', default: 'Bob'));
        self::assertSame('secret', $output->secret('Password?'));
        self::assertTrue($output->isInteractive());
    }

    public function testNoColorDisablesDecoration(): void {
        putenv('NO_COLOR=1');

        $formatter = new OutputFormatter();
        $output = new class($formatter, new Table($formatter), $this->createStub(IPrompt::class), new FixedWidthTerminal()) extends Output {
            public function isDecorated(): bool {
                return $this->isDecorated || $this->isErrorDecorated;
            }
        };

        self::assertFalse($output->isDecorated());
    }
}

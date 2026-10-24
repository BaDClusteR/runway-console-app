<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Unit\Command\ListRenderer;

use PHPUnit\Framework\TestCase;
use Runway\Console\Command\ListRenderer\CommandListRenderer;
use Runway\Console\Tests\Support\BufferedOutput;
use Runway\Console\Tests\Support\CallbackCommand;

class CommandListRendererTest extends TestCase {
    protected BufferedOutput $output;

    protected function setUp(): void {
        $this->output = new BufferedOutput();
    }

    public function testNoCommands(): void {
        (new CommandListRenderer($this->output))->render([]);

        self::assertSame("No commands registered.\n", $this->output->stdout);
    }

    public function testCommandsAreGroupedAndSorted(): void {
        (new CommandListRenderer($this->output))->render([
            new CallbackCommand('db:migrate', 'Run migrations', 'Database'),
            new CallbackCommand('serve', 'Start the server'),
            new CallbackCommand('cache:clear', 'Clear the cache', 'Cache'),
            new CallbackCommand('about', 'About the app'),
            new CallbackCommand('db:dump', 'Dump the database', 'Database'),
        ]);

        // Ungrouped commands go first; the descriptions are aligned in the whole list.
        self::assertSame(
            implode("\n", [
                '',
                'Available commands:',
                '',
                '  about        About the app',
                '  serve        Start the server',
                '',
                'Cache',
                '  cache:clear  Clear the cache',
                '',
                'Database',
                '  db:dump      Dump the database',
                '  db:migrate   Run migrations',
                '',
                'Run "help <command>" or "<command> --help" to see the command usage.',
                '',
            ]) . "\n",
            $this->output->stdout
        );
    }

    public function testOnlyGroupedCommands(): void {
        (new CommandListRenderer($this->output))->render([
            new CallbackCommand('b:two', 'Two', 'B'),
            new CallbackCommand('a:one', 'One', 'A'),
        ]);

        self::assertStringContainsString("Available commands:\n\nA\n  a:one  One\n\nB\n  b:two  Two\n", $this->output->stdout);
    }

    public function testCustomIndent(): void {
        (new CommandListRenderer($this->output, "\t"))->render([new CallbackCommand('run', 'Run')]);

        self::assertStringContainsString("\n\trun  Run\n", $this->output->stdout);
    }

    public function testNamesAndDescriptionsAreEscaped(): void {
        (new CommandListRenderer($this->output))->render([
            new CallbackCommand('make:<fg=red>', 'Makes <options=bold>things</>', '<fg=green>Make</>'),
        ]);

        self::assertStringContainsString(
            "<fg=green>Make</>\n  make:<fg=red>  Makes <options=bold>things</>\n",
            $this->output->stdout
        );
    }

    public function testFormatting(): void {
        (new CommandListRenderer($this->output))->render([
            new CallbackCommand('run', 'Run'),
            new CallbackCommand('db:dump', 'Dump', 'Database'),
        ]);

        self::assertStringContainsString(
            "  <fg=cyan>run</>      Run\n\n<fg=yellow>Database</>\n  <fg=cyan>db:dump</>  Dump\n",
            $this->output->rawStdout
        );
    }

    public function testNumericGroupName(): void {
        // A numeric string becomes an integer array key.
        (new CommandListRenderer($this->output))->render([
            new CallbackCommand('release:notes', 'Notes', '2024'),
            new CallbackCommand('about', 'About'),
        ]);

        self::assertStringContainsString("  about          About\n\n2024\n  release:notes  Notes\n", $this->output->stdout);
    }

    public function testWideNamesAreAligned(): void {
        (new CommandListRenderer($this->output))->render([
            new CallbackCommand('世界', 'Wide'),
            new CallbackCommand('abcdef', 'Narrow'),
        ]);

        self::assertStringContainsString("  abcdef  Narrow\n  世界    Wide\n", $this->output->stdout);
    }
}

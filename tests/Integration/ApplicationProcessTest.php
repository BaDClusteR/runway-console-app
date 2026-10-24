<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Runway\Console\Tests\Support\AppProcess;

/**
 * The app as it's used: the real DI container (the library config, the commands registered by the tag), real STDOUT
 * and STDERR, the exit codes. Without a terminal: the output is redirected, as in scripts and CI.
 */
class ApplicationProcessTest extends TestCase {
    protected const string DEBUG_ENV_FILE = __DIR__ . '/Fixture/debug.env';

    protected static function assertNoEscapeSequences(string $output): void {
        self::assertStringNotContainsString("\033", $output);
        self::assertStringNotContainsString("\r", $output);
    }

    public function testCommandsList(): void {
        $result = AppProcess::run([]);

        self::assertSame(0, $result->exitCode);
        self::assertSame(
            implode("\n", [
                '',
                'Available commands:',
                '',
                '  fail           Fails: with an exception, or with the given exit code',
                '  greet          Greets someone',
                '',
                'Demo',
                '  demo:progress  Shows a progress bar',
                '  demo:prompt    Asks a question and prints the answer',
                '  demo:table     Shows a table',
                '',
                'Run "help <command>" or "<command> --help" to see the command usage.',
                '',
            ]) . "\n",
            $result->stdout
        );
        self::assertSame('', $result->stderr);
    }

    public function testCommand(): void {
        $result = AppProcess::run(['greet', 'John', '-s']);

        self::assertSame([0, "HELLO, JOHN!\n", ''], [$result->exitCode, $result->stdout, $result->stderr]);
    }

    public function testCommandHelp(): void {
        $result = AppProcess::run(['greet', '--help']);

        self::assertSame(0, $result->exitCode);
        self::assertSame(
            "\nGreets someone\n\nUsage:\n  greet [options] <name>\n\nArguments:\n  name  Who to greet\n\nOptions:\n  -s, --shout  Uppercase\n\n",
            $result->stdout
        );
    }

    public function testUnknownCommand(): void {
        $result = AppProcess::run(['unknown']);

        self::assertSame(1, $result->exitCode);
        self::assertSame(" Command \"unknown\" not found. \n", $result->stderr);
        self::assertStringContainsString('Available commands:', $result->stdout);
    }

    public function testInvalidInput(): void {
        // Not a bug: no trace even in the debug mode.
        $result = AppProcess::run(['greet', 'John', '--loud'], ['FIXTURE_ENV_FILE' => static::DEBUG_ENV_FILE]);

        self::assertSame([1, '', " Unknown option: --loud \n"], [$result->exitCode, $result->stdout, $result->stderr]);
    }

    public function testExitCodeAndStreams(): void {
        $result = AppProcess::run(['fail', '--code=3']);

        // The command output and the errors can be redirected separately.
        self::assertSame([3, "Partial result\n", " Finished with errors \n"], [$result->exitCode, $result->stdout, $result->stderr]);
    }

    public function testErrorWithoutDebugMode(): void {
        $result = AppProcess::run(['fail']);

        self::assertSame([1, '', " Something went wrong \n"], [$result->exitCode, $result->stdout, $result->stderr]);
    }

    public function testErrorTraceInDebugMode(): void {
        $result = AppProcess::run(['fail'], ['FIXTURE_ENV_FILE' => static::DEBUG_ENV_FILE]);

        self::assertSame(1, $result->exitCode);
        self::assertSame('', $result->stdout);
        self::assertStringStartsWith(" Something went wrong \nRuntimeException at ", $result->stderr);
        self::assertStringContainsString("\nCaused by: LogicException at ", $result->stderr);
    }

    public function testProgressBarWithoutTerminalOutputsOnlyTheFinalState(): void {
        $result = AppProcess::run(['demo:progress']);

        self::assertSame(0, $result->exitCode);
        self::assertSame(" 3/3 [============================] 100% Done\n", $result->stdout);
    }

    public function testTableWithoutTerminal(): void {
        $result = AppProcess::run(['demo:table']);

        self::assertSame(0, $result->exitCode);
        self::assertSame(
            "┌────┬────────┐\n│ ID │ Status │\n├────┼────────┤\n│ 1  │ ok     │\n│ 2  │ failed │\n└────┴────────┘\n",
            $result->stdout
        );
    }

    public function testNoEscapeSequencesWithoutTerminal(): void {
        foreach ([[], ['demo:table'], ['demo:progress'], ['fail'], ['greet', '--help']] as $arguments) {
            $result = AppProcess::run($arguments);

            static::assertNoEscapeSequences($result->stdout . $result->stderr);
        }
    }

    public function testPromptsWithoutTerminalUseDefaults(): void {
        // STDIN is not a terminal, so the input is not read even if there is something.
        $result = AppProcess::run(['demo:prompt', 'confirm', '--default=yes'], stdin: "n\n");
        self::assertSame([0, "Answer: true\n", ''], [$result->exitCode, $result->stdout, $result->stderr]);

        $result = AppProcess::run(['demo:prompt', 'choice', '--default=cherry']);
        self::assertSame("Answer: 'cherry'\n", $result->stdout);

        $result = AppProcess::run(['demo:prompt', 'ask', '--default=Bob']);
        self::assertSame("Answer: 'Bob'\n", $result->stdout);
    }

    public function testPromptsWithoutTerminalAndDefault(): void {
        foreach (['choice' => 'Fruit?', 'ask' => 'Name?', 'secret' => 'Password?'] as $type => $question) {
            $result = AppProcess::run(['demo:prompt', $type]);

            self::assertSame(1, $result->exitCode, $type);
            self::assertSame('', $result->stdout, $type);
            self::assertSame(
                " {$question}: an answer is required, but the terminal is not interactive. \n",
                $result->stderr,
                $type
            );
        }
    }

    public function testServiceWithCommandTagThatIsNotCommand(): void {
        $expectedError = ' A service with the "console.command" tag is not a command:'
            . " Runway\Console\Tests\Integration\Fixture\NotACommand does not implement Runway\Console\Command\ICommand. \n";

        // The configuration error is shown instead of a fatal error, for the list too.
        foreach ([[], ['greet', 'John']] as $arguments) {
            $result = AppProcess::run($arguments, ['FIXTURE_INVALID_CONFIG' => '1']);

            self::assertSame([1, '', $expectedError], [$result->exitCode, $result->stdout, $result->stderr]);
        }
    }

    public function testDefaultAnswerIsValidated(): void {
        $result = AppProcess::run(['demo:prompt', 'ask', '--default=']);

        self::assertSame([1, " The name is required. \n"], [$result->exitCode, $result->stderr]);
    }
}

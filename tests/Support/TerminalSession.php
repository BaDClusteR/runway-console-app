<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Support;

use PHPUnit\Framework\Assert;
use RuntimeException;

/**
 * Runs the fixture app (tests/Integration/Fixture/app.php) in a pseudo-terminal via "script": the app gets a real
 * controlling terminal (raw mode, stty), and the test types the keys like a user.
 */
class TerminalSession {
    protected const float TIMEOUT = 5.0;

    /** @var non-empty-string */
    protected const string SETTINGS_MARKER = '<<<TERMINAL SETTINGS>>>';

    /** @var resource */
    protected $process;

    /** @var resource[] */
    protected array $pipes;

    protected string $output = '';

    protected ?int $exitCode = null;

    /**
     * @param list<string>          $arguments
     * @param array<string, string> $env Added to the current environment.
     */
    public function __construct(
        array            $arguments,
        protected int    $columns = 60,
        int              $rows = 20,
        array            $env = []
    ) {
        $appCommand = implode(' ', array_map(escapeshellarg(...), [PHP_BINARY, AppProcess::APP, ...$arguments]));
        // The terminal settings after the app are printed, to check they are restored.
        $shellCommand = "stty cols {$columns} rows {$rows}; {$appCommand}; code=\$?; "
            . 'echo; echo ' . escapeshellarg(static::SETTINGS_MARKER) . '; stty -a; exit $code';

        $process = proc_open(
            static::getScriptCommand($shellCommand),
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            null,
            [...getenv(), ...$env]
        );

        if ($process === false) {
            throw new RuntimeException('The terminal session cannot be started.');
        }

        $this->process = $process;
        $this->pipes = $pipes;

        stream_set_blocking($this->pipes[1], false);
    }

    public function __destruct() {
        if ($this->exitCode === null) {
            proc_terminate($this->process);
            $this->exitCode = -1;
        }
    }

    public static function isAvailable(): bool {
        return in_array(PHP_OS_FAMILY, ['Darwin', 'Linux', 'BSD'], true)
            && trim((string)shell_exec('command -v script 2>/dev/null')) !== '';
    }

    /**
     * @return list<string>
     */
    protected static function getScriptCommand(string $shellCommand): array {
        // BSD (macOS) and util-linux versions of "script" have different arguments.
        return (PHP_OS_FAMILY === 'Linux')
            ? ['script', '-qefc', $shellCommand, '/dev/null']
            : ['script', '-q', '/dev/null', 'sh', '-c', $shellCommand];
    }

    /**
     * Waits until the text is output (e.g. a prompt is shown).
     *
     * @param int $count How many times, e.g. to wait for a prompt asked again.
     */
    public function waitFor(string $text, int $count = 1): static {
        $this->readUntil(
            fn(): bool => substr_count($this->output, $text) >= $count,
            json_encode($text) . " is not output {$count} time(s)"
        );

        return $this;
    }

    /**
     * Presses the keys one by one: every key press is read separately and redraws the prompt.
     */
    public function press(string ...$keys): static {
        foreach ($keys as $key) {
            $outputLength = strlen($this->output);

            fwrite($this->pipes[0], $key);
            fflush($this->pipes[0]);

            $this->readUntil(
                fn(): bool => strlen($this->output) > $outputLength,
                'no output after the key ' . json_encode($key)
            );
        }

        return $this;
    }

    /**
     * Waits for the app to exit.
     *
     * @return int The exit code.
     */
    public function finish(): int {
        $this->readUntil(fn(): bool => !proc_get_status($this->process)['running'], 'the app does not exit');

        $this->exitCode = proc_get_status($this->process)['exitcode'];
        $this->output .= stream_get_contents($this->pipes[1]);

        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }

        proc_close($this->process);

        return $this->exitCode;
    }

    /**
     * Everything the app wrote to the terminal (STDOUT and STDERR).
     */
    public function getOutput(): string {
        return explode(static::SETTINGS_MARKER, $this->output)[0];
    }

    /**
     * @return string[] What the user sees.
     */
    public function getScreen(): array {
        return VirtualScreen::render($this->getOutput(), $this->columns);
    }

    /**
     * @return string[] The terminal flags after the app (the words of "stty -a", e.g. "icanon" or "-echo").
     */
    public function getSettingsAfterExit(): array {
        Assert::assertNotNull($this->exitCode, 'The app is still running.');

        $settings = explode(static::SETTINGS_MARKER, $this->output)[1] ?? '';

        return preg_split('/[\s;]+/', $settings, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @param callable(): bool $isDone
     */
    protected function readUntil(callable $isDone, string $failure): void {
        $deadline = microtime(true) + static::TIMEOUT;

        while (!$isDone()) {
            if (microtime(true) > $deadline) {
                Assert::fail("Timeout: {$failure}. The output:\n" . $this->output);
            }

            $read = [$this->pipes[1]];
            $write = $except = null;

            if (stream_select($read, $write, $except, 0, 20000)) {
                $this->output .= (string)fread($this->pipes[1], 65536);
            }
        }
    }
}

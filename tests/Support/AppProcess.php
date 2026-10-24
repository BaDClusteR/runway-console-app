<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Support;

use RuntimeException;

/**
 * Runs the fixture app (tests/Integration/Fixture/app.php) in a separate process: STDIN, STDOUT and STDERR are pipes,
 * so there is no terminal.
 */
readonly class AppProcess {
    public const string APP = __DIR__ . '/../Integration/Fixture/app.php';

    public function __construct(
        public int    $exitCode,
        public string $stdout,
        public string $stderr
    ) {}

    /**
     * @param string[]              $arguments
     * @param array<string, string> $env Added to the current environment.
     */
    public static function run(array $arguments, array $env = [], string $stdin = ''): static {
        $process = proc_open(
            [PHP_BINARY, static::APP, ...$arguments],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            null,
            [...getenv(), ...$env]
        );

        if ($process === false) {
            throw new RuntimeException('The app cannot be started.');
        }

        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);

        // The outputs are small, so reading them one by one does not block the process.
        $stdout = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return new static(proc_close($process), $stdout, $stderr);
    }
}

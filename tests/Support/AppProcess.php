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
     * @param list<string>          $arguments
     * @param array<string, string> $env Added to the current environment.
     */
    public static function run(array $arguments, array $env = [], string $stdin = ''): self {
        $process = proc_open(
            [PHP_BINARY, self::APP, ...$arguments],
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

        // The lines end with "\n" on every platform.
        return new self(proc_close($process), str_replace(PHP_EOL, "\n", $stdout), str_replace(PHP_EOL, "\n", $stderr));
    }
}

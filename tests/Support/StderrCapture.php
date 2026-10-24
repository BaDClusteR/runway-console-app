<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Support;

use php_user_filter;
use RuntimeException;

/**
 * Captures what is written to STDERR (and keeps it out of the test runner output) with a stream filter.
 */
class StderrCapture extends php_user_filter {
    protected const string FILTER_NAME = 'runway_console_tests.stderr_capture';

    protected static string $buffer = '';

    /** @var resource|null */
    protected static $filter = null;

    public static function start(): void {
        if (!in_array(static::FILTER_NAME, stream_get_filters(), true)) {
            stream_filter_register(static::FILTER_NAME, static::class);
        }

        static::$buffer = '';
        static::$filter = stream_filter_append(STDERR, static::FILTER_NAME, STREAM_FILTER_WRITE)
            ?: throw new RuntimeException('STDERR cannot be captured.');
    }

    /**
     * @return string Everything written since start().
     */
    public static function stop(): string {
        if (static::$filter !== null) {
            stream_filter_remove(static::$filter);
            static::$filter = null;
        }

        return static::$buffer;
    }

    public function filter($in, $out, &$consumed, bool $closing): int {
        while ($bucket = stream_bucket_make_writeable($in)) {
            static::$buffer .= $bucket->data;
            $consumed += $bucket->datalen;
        }

        // Nothing is passed on: the data does not reach the real STDERR.
        return PSFS_PASS_ON;
    }
}

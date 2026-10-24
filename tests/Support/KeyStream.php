<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Support;

/**
 * An input stream that returns one chunk per read, like a terminal in the raw mode returns one key press (or one
 * pasted text). The end of the chunks is the end of the input.
 */
class KeyStream {
    protected const string PROTOCOL = 'runway-console-tests-keys';

    /** @var string[] */
    protected static array $chunks = [];

    /** @var resource|null */
    public $context;

    /**
     * @param string[] $chunks
     *
     * @return resource
     */
    public static function open(array $chunks) {
        if (!in_array(static::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_register(static::PROTOCOL, static::class);
        }

        static::$chunks = $chunks;

        return fopen(static::PROTOCOL . '://input', 'r');
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool {
        return true;
    }

    public function stream_read(int $count): string {
        return array_shift(static::$chunks) ?? '';
    }

    public function stream_eof(): bool {
        return static::$chunks === [];
    }
}

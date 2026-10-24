<?php

declare(strict_types=1);

namespace Runway\Console\Output\Formatter;

class OutputFormatter implements IOutputFormatter {
    /** @var array<string, string> */
    protected const array FG_COLORS = [
        'black'   => '30',
        'red'     => '31',
        'green'   => '32',
        'yellow'  => '33',
        'blue'    => '34',
        'magenta' => '35',
        'cyan'    => '36',
        'white'   => '37',
        'default' => '39',
    ];

    /** @var array<string, string> */
    protected const array BG_COLORS = [
        'black'   => '40',
        'red'     => '41',
        'green'   => '42',
        'yellow'  => '43',
        'blue'    => '44',
        'magenta' => '45',
        'cyan'    => '46',
        'white'   => '47',
        'default' => '49',
    ];

    /** @var array<string, string> */
    protected const array OPTIONS = [
        'bold'       => '1',
        'dim'        => '2',
        'underscore' => '4',
        'reverse'    => '7',
    ];

    /** @var non-empty-string */
    protected const string ESCAPED_TAG_START = '\\<';

    protected const string CONTROL_CHAR_REPLACEMENT = "\u{FFFD}";

    public function format(string $message, bool $isDecorated = true): string {
        // Escaped "<" are split out, so they can never be a part of a tag.
        $pieces = explode(static::ESCAPED_TAG_START, $message);

        foreach ($pieces as &$piece) {
            $piece = preg_replace_callback(
                '/<((?:fg|bg|options)=[a-z,]+(?:;(?:fg|bg|options)=[a-z,]+)*)>/',
                fn(array $matches): string => $isDecorated ? $this->parseTag($matches[1]) : '',
                str_replace('</>', $isDecorated ? "\033[0m" : '', $piece)
            );
        }

        return implode('<', $pieces);
    }

    public function escape(string $text): string {
        return str_replace(
            '<',
            static::ESCAPED_TAG_START,
            (string)preg_replace(
                // C1 controls (U+0080-U+009F) too: e.g. U+009B is CSI ("\033[") for some terminals.
                '/[\x{00}-\x{08}\x{0B}-\x{1F}\x{7F}-\x{9F}]/u',
                static::CONTROL_CHAR_REPLACEMENT,
                $this->replaceInvalidUtf8(str_replace("\r\n", "\n", $text))
            )
        );
    }

    /**
     * Invalid bytes are replaced, as a raw byte can be a control too (e.g. 0x9B is CSI in the 8-bit mode).
     */
    protected function replaceInvalidUtf8(string $text): string {
        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        $substituteCharacter = mb_substitute_character();
        mb_substitute_character(mb_ord(static::CONTROL_CHAR_REPLACEMENT));

        try {
            return mb_scrub($text, 'UTF-8');
        } finally {
            mb_substitute_character($substituteCharacter);
        }
    }

    protected function parseTag(string $tagContent): string {
        $codes = [];
        $parts = explode(';', $tagContent);

        foreach ($parts as $part) {
            [$type, $color] = explode('=', $part, 2);

            if ($type === 'fg' && isset(static::FG_COLORS[$color])) {
                $codes[] = static::FG_COLORS[$color];
            } elseif ($type === 'bg' && isset(static::BG_COLORS[$color])) {
                $codes[] = static::BG_COLORS[$color];
            } elseif ($type === 'options') {
                foreach (explode(',', $color) as $option) {
                    if (isset(static::OPTIONS[$option])) {
                        $codes[] = static::OPTIONS[$option];
                    }
                }
            }
        }

        if ($codes === []) {
            return '';
        }

        return "\033[" . implode(';', $codes) . 'm';
    }
}

<?php

declare(strict_types=1);

namespace Runway\Console\Output\Formatter;

class OutputFormatter implements IOutputFormatter {
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

    public function format(string $message): string {
        $message = str_replace('</>', "\033[0m", $message);

        return preg_replace_callback(
            '/<((?:fg|bg)=[a-z]+(?:;(?:fg|bg)=[a-z]+)*)>/',
            fn(array $matches): string => $this->parseTag($matches[1]),
            $message
        );
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
            }
        }

        if ($codes === []) {
            return '';
        }

        return "\033[" . implode(';', $codes) . 'm';
    }
}

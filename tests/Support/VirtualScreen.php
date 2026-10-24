<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Support;

/**
 * A minimal terminal emulator: shows what the user would see after the output. Supports the text (wide characters
 * take two columns), "\r", "\n", the cursor movement up/down, the line and screen clearing, and the line wrapping;
 * the colors and other sequences are ignored.
 */
class VirtualScreen {
    /** @var array<int, array<int, string>> Row => column => character ("" for the second column of a wide one). */
    protected array $cells = [];

    protected int $row = 0;

    protected int $column = 0;

    public function __construct(
        protected int $width = 1000
    ) {}

    /**
     * @return string[] The screen lines, without the trailing spaces.
     */
    public static function render(string $output, int $width = 1000): array {
        $screen = new static($width);
        $screen->write($output);

        return $screen->getLines();
    }

    public function write(string $output): void {
        preg_match_all('/\033\[([0-9;?]*)([A-Za-z])|\033\][^\x07]*\x07|\r|\n|\X/u', $output, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $token = $match[0];

            if ($token === "\r") {
                $this->column = 0;
            } elseif ($token === "\n") {
                // Like a terminal with the default settings (onlcr): a new line starts from the first column.
                $this->row++;
                $this->column = 0;
            } elseif (isset($match[2])) {
                $this->handleSequence($match[1], $match[2]);
            } elseif (!str_starts_with($token, "\033")) {
                $this->writeCharacter($token);
            }
        }
    }

    /**
     * @return string[]
     */
    public function getLines(): array {
        if ($this->cells === []) {
            return [];
        }

        $lines = [];

        for ($row = 0, $lastRow = max(array_keys($this->cells)); $row <= $lastRow; $row++) {
            $cells = $this->cells[$row] ?? [];
            $line = '';

            for ($column = 0, $lastColumn = $cells ? max(array_keys($cells)) : -1; $column <= $lastColumn; $column++) {
                $line .= $cells[$column] ?? ' ';
            }

            $lines[] = rtrim($line, ' ');
        }

        // Empty lines at the bottom are not visible.
        while ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    protected function handleSequence(string $parameters, string $command): void {
        $count = max(1, (int)$parameters);

        match ($command) {
            'A'     => $this->row = max(0, $this->row - $count),
            'B'     => $this->row += $count,
            'C'     => $this->column += $count,
            'D'     => $this->column = max(0, $this->column - $count),
            'K'     => $this->clearLineFromCursor(),
            'J'     => $this->clearScreenFromCursor(),
            default => null,
        };
    }

    protected function writeCharacter(string $character): void {
        $characterWidth = mb_strwidth($character);

        if ($this->column + $characterWidth > $this->width) {
            $this->row++;
            $this->column = 0;
        }

        $this->cells[$this->row][$this->column] = $character;

        for ($i = 1; $i < $characterWidth; $i++) {
            $this->cells[$this->row][$this->column + $i] = '';
        }

        $this->column += $characterWidth;
    }

    protected function clearLineFromCursor(): void {
        foreach (array_keys($this->cells[$this->row] ?? []) as $column) {
            if ($column >= $this->column) {
                unset($this->cells[$this->row][$column]);
            }
        }
    }

    protected function clearScreenFromCursor(): void {
        $this->clearLineFromCursor();

        foreach (array_keys($this->cells) as $row) {
            if ($row > $this->row) {
                unset($this->cells[$row]);
            }
        }
    }
}

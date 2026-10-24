<?php

declare(strict_types=1);

namespace Runway\Console\Output\Table;

use Runway\Console\Output\Formatter\IOutputFormatter;

class Table implements ITable {
    protected const string HORIZONTAL = '─';

    protected const string VERTICAL = '│';

    /** Left, middle and right junctions of the top border. */
    protected const array TOP = ['┌', '┬', '┐'];

    /** The same for the line between the header and the rows. */
    protected const array MIDDLE = ['├', '┼', '┤'];

    /** The same for the bottom border. */
    protected const array BOTTOM = ['└', '┴', '┘'];

    public function __construct(
        protected IOutputFormatter $formatter
    ) {}

    public function render(array $headers, array $rows): string {
        $columnWidths = $this->calculateColumnWidths($headers, $rows);

        $output = $this->buildSeparator($columnWidths, static::TOP);
        $output .= $this->buildRow($headers, $columnWidths);

        // Without rows, the header line would be followed by two borders.
        if ($rows !== []) {
            $output .= $this->buildSeparator($columnWidths, static::MIDDLE);
        }

        foreach ($rows as $row) {
            $output .= $this->buildRow($row, $columnWidths);
        }

        $output .= $this->buildSeparator($columnWidths, static::BOTTOM);

        return $output;
    }

    /**
     * @param string[]   $headers
     * @param string[][] $rows
     * @return int[]
     */
    protected function calculateColumnWidths(array $headers, array $rows): array {
        $widths = array_map(
            fn(string $header): int => $this->getVisibleWidth($header),
            $headers
        );

        foreach ($rows as $row) {
            foreach ($row as $colIndex => $cell) {
                $cellLength = $this->getVisibleWidth($this->getCellText($cell));

                if (!isset($widths[$colIndex]) || $cellLength > $widths[$colIndex]) {
                    $widths[$colIndex] = $cellLength;
                }
            }
        }

        return $widths;
    }

    /**
     * @param int[]    $columnWidths
     * @param string[] $junctions Left, middle and right junctions (see TOP, MIDDLE, BOTTOM).
     */
    protected function buildSeparator(array $columnWidths, array $junctions): string {
        [$left, $middle, $right] = $junctions;

        $parts = array_map(
            static fn(int $width): string => str_repeat(static::HORIZONTAL, $width + 2),
            $columnWidths
        );

        return $left . implode($middle, $parts) . $right . PHP_EOL;
    }

    /**
     * @param string[] $row
     * @param int[]    $columnWidths
     */
    protected function buildRow(array $row, array $columnWidths): string {
        $cells = [];

        foreach ($columnWidths as $colIndex => $width) {
            $cellValue = $this->getCellText($row[$colIndex] ?? '');
            $padding = max(0, $width - $this->getVisibleWidth($cellValue));
            $cells[] = ' ' . $cellValue . str_repeat(' ', $padding) . ' ';
        }

        return static::VERTICAL . implode(static::VERTICAL, $cells) . static::VERTICAL . PHP_EOL;
    }

    /**
     * Line breaks would break the table layout, so they are replaced with spaces.
     */
    protected function getCellText(mixed $cell): string {
        return (string)preg_replace('/\R/u', ' ', (string)$cell);
    }

    /**
     * Width in terminal columns: wide characters (CJK, most emoji) take two columns, while formatting tags and
     * escape sequences are not displayed at all. The text is formatted without the decoration, so the tags are
     * removed exactly like the formatter parses them (e.g. "<options=bold>", escaped "<").
     */
    protected function getVisibleWidth(string $text): int {
        return mb_strwidth(
            $this->formatter->format($text, false)
        );
    }
}

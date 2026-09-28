<?php

declare(strict_types=1);

namespace Runway\Console\Output\Table;

class Table implements ITable {
    public function render(array $headers, array $rows): string {
        $columnWidths = $this->calculateColumnWidths($headers, $rows);

        $output = '';
        $separator = $this->buildSeparator($columnWidths);

        $output .= $separator;
        $output .= $this->buildRow($headers, $columnWidths);
        $output .= $separator;

        foreach ($rows as $row) {
            $output .= $this->buildRow($row, $columnWidths);
        }

        $output .= $separator;

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
     * @param int[] $columnWidths
     */
    protected function buildSeparator(array $columnWidths): string {
        $parts = array_map(
            static fn(int $width): string => str_repeat('-', $width + 2),
            $columnWidths
        );

        return '+' . implode('+', $parts) . '+' . PHP_EOL;
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

        return '|' . implode('|', $cells) . '|' . PHP_EOL;
    }

    /**
     * Line breaks would break the table layout, so they are replaced with spaces.
     */
    protected function getCellText(mixed $cell): string {
        return (string)preg_replace('/\R/u', ' ', (string)$cell);
    }

    /**
     * Width in terminal columns: wide characters (CJK, most emoji) take two columns, while formatting tags and
     * escape sequences are not displayed at all.
     */
    protected function getVisibleWidth(string $text): int {
        $text = (string)preg_replace('/<(?:fg|bg)=[a-z]+(?:;(?:fg|bg)=[a-z]+)*>|<\/>/', '', $text);

        return mb_strwidth(
            str_replace('\\<', '<', $text)
        );
    }
}

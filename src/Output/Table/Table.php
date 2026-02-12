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
            static fn(string $header): int => mb_strlen($header),
            $headers
        );

        foreach ($rows as $row) {
            foreach ($row as $colIndex => $cell) {
                $cellLength = mb_strlen((string)$cell);

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
            $cellValue = (string)($row[$colIndex] ?? '');
            $cells[] = ' ' . str_pad($cellValue, $width) . ' ';
        }

        return '|' . implode('|', $cells) . '|' . PHP_EOL;
    }
}

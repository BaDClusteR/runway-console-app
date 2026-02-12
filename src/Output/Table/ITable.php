<?php

declare(strict_types=1);

namespace Runway\Console\Output\Table;

interface ITable {
    /**
     * @param string[]   $headers
     * @param string[][] $rows
     */
    public function render(array $headers, array $rows): string;
}

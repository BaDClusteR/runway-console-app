<?php

declare(strict_types=1);

namespace Runway\Console\Output\Table;

interface ITable {
    /**
     * The headers and the cells are formatted: escape the user data (see IOutputFormatter::escape()).
     *
     * @param string[]                              $headers
     * @param array<array<scalar|\Stringable|null>> $rows
     */
    public function render(array $headers, array $rows): string;
}

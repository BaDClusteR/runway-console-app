<?php

declare(strict_types=1);

namespace Runway\Console;

use Runway\ISingleton;

interface IApplication extends ISingleton {
    public function run(): int;
}

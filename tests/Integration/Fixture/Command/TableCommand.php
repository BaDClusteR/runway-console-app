<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Integration\Fixture\Command;

use Runway\Console\Command\ACommand;
use Runway\Console\Input\IInput;
use Runway\Console\Output\IOutput;

class TableCommand extends ACommand {
    public function getName(): string {
        return 'demo:table';
    }

    public function getDescription(): string {
        return 'Shows a table';
    }

    public function getGroup(): ?string {
        return 'Demo';
    }

    protected function execute(IInput $input, IOutput $output): int {
        $output->table(
            ['ID', 'Status'],
            [['1', '<fg=green>ok</>'], ['2', '<fg=red>failed</>']]
        );

        return 0;
    }
}

<?php

declare(strict_types=1);

namespace Runway\Console\Command\ListRenderer;

use Runway\Console\Command\ICommand;

interface ICommandListRenderer {
    /**
     * Outputs the list of commands: ungrouped commands first, then groups (see ICommand::getGroup()) sorted by name.
     *
     * @param ICommand[] $commands
     */
    public function render(array $commands): void;
}

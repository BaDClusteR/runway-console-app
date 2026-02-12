<?php

declare(strict_types=1);

namespace Runway\Console\Exception;

class CommandNotFoundException extends ConsoleException {
    public function __construct(
        public readonly string $commandName
    ) {
        parent::__construct(
            "Command \"{$commandName}\" not found."
        );
    }
}

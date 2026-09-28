<?php

declare(strict_types=1);

namespace Runway\Console\Command;

use Runway\Console\Input\IInput;
use Runway\Console\Output\IOutput;
use Runway\Console\Parameter\DTO\ParameterDTO;

interface ICommand {
    public function getName(): string;

    public function getDescription(): string;

    /**
     * Group the command is shown in the commands list. Null means no group: such commands are shown first.
     */
    public function getGroup(): ?string;

    /**
     * @return ParameterDTO[]
     */
    public function getParameters(): array;

    public function run(IInput $input, IOutput $output): int;
}

<?php

declare(strict_types=1);

namespace Runway\Console\Command;

use Runway\Console\Input\IInput;
use Runway\Console\Output\IOutput;
use Runway\Console\Parameter\DTO\ParameterDTO;
use Runway\Console\Parameter\Enum\ParameterModeEnum;
use Runway\Console\Parameter\Enum\ParameterTypeEnum;

abstract class ACommand implements ICommand {
    /** @var ParameterDTO[] */
    private array $parameters = [];

    public function __construct() {
        $this->configure();
    }

    protected function configure(): void {}

    abstract public function getName(): string;

    abstract public function getDescription(): string;

    abstract protected function execute(IInput $input, IOutput $output): int;

    public function run(IInput $input, IOutput $output): int {
        return $this->execute($input, $output);
    }

    /**
     * @return ParameterDTO[]
     */
    public function getParameters(): array {
        return $this->parameters;
    }

    protected function addArgument(
        string            $name,
        ParameterModeEnum $mode = ParameterModeEnum::REQUIRED,
        string            $description = '',
        ?string           $default = null
    ): static {
        $this->parameters[] = new ParameterDTO(
            name: $name,
            type: ParameterTypeEnum::ARGUMENT,
            mode: $mode,
            description: $description,
            default: $default
        );

        return $this;
    }

    protected function addOption(
        string            $name,
        ?string           $shortcut = null,
        ParameterModeEnum $mode = ParameterModeEnum::VALUE_NONE,
        string            $description = '',
        ?string           $default = null
    ): static {
        $this->parameters[] = new ParameterDTO(
            name: $name,
            type: ParameterTypeEnum::OPTION,
            mode: $mode,
            description: $description,
            default: $default,
            shortcut: $shortcut
        );

        return $this;
    }
}

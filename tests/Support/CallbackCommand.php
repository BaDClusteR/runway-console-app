<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Support;

use Closure;
use Runway\Console\Command\ACommand;
use Runway\Console\Input\IInput;
use Runway\Console\Output\IOutput;
use Runway\Console\Parameter\Enum\ParameterModeEnum;

/**
 * A command configured in the test itself.
 */
class CallbackCommand extends ACommand {
    /** @var IInput[] The input of every run. */
    public array $runs = [];

    /**
     * @param (Closure(IInput, IOutput): int)|null $handler Returns 0 by default.
     */
    public function __construct(
        protected string   $name,
        protected string   $description = '',
        protected ?string  $group = null,
        protected ?Closure $handler = null
    ) {}

    public function getName(): string {
        return $this->name;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function getGroup(): ?string {
        return $this->group;
    }

    public function withArgument(
        string            $name,
        ParameterModeEnum $mode = ParameterModeEnum::REQUIRED,
        string            $description = '',
        ?string           $default = null
    ): static {
        return $this->addArgument($name, $mode, $description, $default);
    }

    public function withOption(
        string            $name,
        ?string           $shortcut = null,
        ParameterModeEnum $mode = ParameterModeEnum::VALUE_NONE,
        string            $description = '',
        ?string           $default = null
    ): static {
        return $this->addOption($name, $shortcut, $mode, $description, $default);
    }

    protected function execute(IInput $input, IOutput $output): int {
        $this->runs[] = $input;

        return $this->handler ? ($this->handler)($input, $output) : 0;
    }
}

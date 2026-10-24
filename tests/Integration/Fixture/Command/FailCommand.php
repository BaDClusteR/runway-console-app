<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Integration\Fixture\Command;

use LogicException;
use Runway\Console\Command\ACommand;
use Runway\Console\Input\IInput;
use Runway\Console\Output\IOutput;
use Runway\Console\Parameter\Enum\ParameterModeEnum;
use RuntimeException;

class FailCommand extends ACommand {
    public function getName(): string {
        return 'fail';
    }

    public function getDescription(): string {
        return 'Fails: with an exception, or with the given exit code';
    }

    protected function configure(): void {
        $this->addOption('code', mode: ParameterModeEnum::VALUE_REQUIRED, description: 'Exit code');
    }

    protected function execute(IInput $input, IOutput $output): int {
        $code = $input->getOption('code');

        if ($code === null) {
            throw new RuntimeException('Something went wrong', 0, new LogicException('Root cause'));
        }

        $output->writeln('Partial result');
        $output->warning('Finished with errors');

        return (int)$code;
    }
}

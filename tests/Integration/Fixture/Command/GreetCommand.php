<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Integration\Fixture\Command;

use Runway\Console\Command\ACommand;
use Runway\Console\Input\IInput;
use Runway\Console\Output\IOutput;

class GreetCommand extends ACommand {
    public function getName(): string {
        return 'greet';
    }

    public function getDescription(): string {
        return 'Greets someone';
    }

    protected function configure(): void {
        $this
            ->addArgument('name', description: 'Who to greet')
            ->addOption('shout', 's', description: 'Uppercase');
    }

    protected function execute(IInput $input, IOutput $output): int {
        $text = 'Hello, ' . $input->getArgument('name') . '!';

        $output->writeln($output->escape($input->getOption('shout') ? strtoupper($text) : $text));

        return 0;
    }
}

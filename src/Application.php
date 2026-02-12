<?php

declare(strict_types=1);

namespace Runway\Console;

use Runway\Console\Command\ICommand;
use Runway\Console\Exception\CommandNotFoundException;
use Runway\Console\Input\Parser\IInputParser;
use Runway\Console\Output\IOutput;
use Runway\Event\IEventDispatcher;
use Runway\Singleton;
use Runway\Singleton\Container;
use Throwable;

class Application extends Singleton implements IApplication {
    public function __construct(
        protected IEventDispatcher $eventDispatcher,
        protected IInputParser     $inputParser,
        protected IOutput          $output
    ) {}

    public function run(): int {
        $this->eventDispatcher->dispatch('kernel.init', null);

        $argv = $_SERVER['argv'] ?? [];
        $commandName = $argv[1] ?? null;

        if ($commandName === null || $commandName === 'list') {
            $this->listCommands();
            return 0;
        }

        try {
            $command = $this->findCommand($commandName);

            $input = $this->inputParser->parse(
                array_slice($argv, 2),
                $command->getParameters()
            );

            return $command->run($input, $this->output);
        } catch (CommandNotFoundException $e) {
            $this->output->error($e->getMessage());
            $this->listCommands();
            return 1;
        } catch (Throwable $e) {
            $this->output->error($e->getMessage());
            return 1;
        }
    }

    /**
     * @return ICommand[]
     */
    protected function getCommands(): array {
        return Container::getInstance()->getServicesByTag('console.command');
    }

    protected function findCommand(string $name): ICommand {
        foreach ($this->getCommands() as $command) {
            if ($command->getName() === $name) {
                return $command;
            }
        }

        throw new CommandNotFoundException($name);
    }

    protected function listCommands(): void {
        $commands = $this->getCommands();

        if ($commands === []) {
            $this->output->writeln('No commands registered.');
            return;
        }

        usort(
            $commands,
            static fn(ICommand $a, ICommand $b): int => strcmp($a->getName(), $b->getName())
        );

        $maxNameLength = 0;

        foreach ($commands as $command) {
            $nameLen = mb_strlen($command->getName());

            if ($nameLen > $maxNameLength) {
                $maxNameLength = $nameLen;
            }
        }

        $this->output->writeln('');
        $this->output->writeln('<fg=green>Available commands:</>');
        $this->output->writeln('');

        foreach ($commands as $command) {
            $paddedName = str_pad($command->getName(), $maxNameLength + 2);

            $this->output->writeln(
                "  <fg=cyan>{$paddedName}</>{$command->getDescription()}"
            );
        }

        $this->output->writeln('');
    }
}

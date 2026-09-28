<?php

declare(strict_types=1);

namespace Runway\Console;

use Runway\Console\Command\ICommand;
use Runway\Console\Command\ListRenderer\ICommandListRenderer;
use Runway\Console\Exception\CommandNotFoundException;
use Runway\Console\Exception\ConsoleException;
use Runway\Console\Input\Parser\IInputParser;
use Runway\Console\Output\IOutput;
use Runway\Console\Parameter\DTO\ParameterDTO;
use Runway\Console\Parameter\Enum\ParameterModeEnum;
use Runway\Console\Parameter\Enum\ParameterTypeEnum;
use Runway\Env\Provider\IEnvVariablesProvider;
use Runway\Event\IEventDispatcher;
use Runway\Singleton;
use Runway\Singleton\Container;
use Throwable;

class Application extends Singleton implements IApplication {
    protected const array HELP_OPTIONS = ['--help', '-h'];

    public function __construct(
        protected IEventDispatcher      $eventDispatcher,
        protected IInputParser          $inputParser,
        protected IOutput               $output,
        protected IEnvVariablesProvider $envVariablesProvider,
        protected ICommandListRenderer  $commandListRenderer
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
            if ($commandName === 'help') {
                return $this->showHelp($argv[2] ?? null);
            }

            $command = $this->findCommand($commandName);
            $tokens = array_slice($argv, 2);

            if (array_intersect($tokens, static::HELP_OPTIONS)) {
                $this->showCommandHelp($command);
                return 0;
            }

            $input = $this->inputParser->parse(
                $tokens,
                $command->getParameters()
            );

            return $command->run($input, $this->output);
        } catch (CommandNotFoundException $e) {
            $this->output->error($e->getMessage());
            $this->listCommands();
            return 1;
        } catch (Throwable $e) {
            $this->renderException($e);
            return 1;
        }
    }

    protected function renderException(Throwable $e): void {
        $this->output->error($this->output->escape($e->getMessage()));

        // Input errors (unknown option, missing argument, etc.) are not bugs, so the trace is useless there.
        if (!$this->isDebugMode() || $e instanceof ConsoleException) {
            return;
        }

        for ($exception = $e; $exception; $exception = $exception->getPrevious()) {
            $this->output->writeErrorLine(
                $this->output->escape(
                    sprintf(
                        "%s%s at %s:%d\n%s",
                        ($exception === $e) ? "" : "Caused by: ",
                        $exception::class,
                        $exception->getFile(),
                        $exception->getLine(),
                        $exception->getTraceAsString()
                    )
                )
            );
        }
    }

    protected function isDebugMode(): bool {
        return $this->envVariablesProvider->getEnvVariable('APP_DEBUG') === true;
    }

    protected function showHelp(?string $commandName): int {
        if ($commandName === null) {
            $this->listCommands();
            return 0;
        }

        $this->showCommandHelp(
            $this->findCommand($commandName)
        );

        return 0;
    }

    protected function showCommandHelp(ICommand $command): void {
        $parameters = $command->getParameters();
        $arguments = $this->filterParameters($parameters, ParameterTypeEnum::ARGUMENT);
        $options = $this->filterParameters($parameters, ParameterTypeEnum::OPTION);

        $usage = $command->getName();

        if ($options) {
            $usage .= ' [options]';
        }

        foreach ($arguments as $argument) {
            $usage .= ($argument->getMode() === ParameterModeEnum::REQUIRED)
                ? " <{$argument->getName()}>"
                : " [<{$argument->getName()}>]";
        }

        $this->output->writeln('');
        $this->output->writeln($command->getDescription());
        $this->output->writeln('');
        $this->output->writeln('<fg=green>Usage:</>');
        $this->output->writeln('  ' . $this->output->escape($usage));

        if ($arguments) {
            $this->output->writeln('');
            $this->output->writeln('<fg=green>Arguments:</>');
            $this->writeParameterList(
                array_map(
                    static fn(ParameterDTO $argument): array => [$argument->getName(), $argument],
                    $arguments
                )
            );
        }

        if ($options) {
            $this->output->writeln('');
            $this->output->writeln('<fg=green>Options:</>');
            $this->writeParameterList(
                array_map(
                    fn(ParameterDTO $option): array => [$this->getOptionSignature($option), $option],
                    $options
                )
            );
        }

        $this->output->writeln('');
    }

    /**
     * @param array{0: string, 1: ParameterDTO}[] $list Signature and parameter pairs.
     */
    protected function writeParameterList(array $list): void {
        $maxLength = max(
            array_map(
                static fn(array $item): int => mb_strlen($item[0]),
                $list
            )
        );

        foreach ($list as [$signature, $parameter]) {
            $description = $parameter->getDescription();

            if ($parameter->getDefault() !== null) {
                $description .= " [default: \"{$parameter->getDefault()}\"]";
            }

            $this->output->writeln(
                '  <fg=cyan>' . $this->output->escape(str_pad($signature, $maxLength + 2)) . '</>'
                . $this->output->escape($description)
            );
        }
    }

    protected function getOptionSignature(ParameterDTO $option): string {
        $signature = ($option->getShortcut() !== null)
            ? "-{$option->getShortcut()}, "
            : '    ';

        $signature .= "--{$option->getName()}";

        return match ($option->getMode()) {
            ParameterModeEnum::VALUE_REQUIRED => "{$signature}=VALUE",
            ParameterModeEnum::VALUE_OPTIONAL => "{$signature}[=VALUE]",
            default                           => $signature,
        };
    }

    /**
     * @param ParameterDTO[] $parameters
     *
     * @return ParameterDTO[]
     */
    protected function filterParameters(array $parameters, ParameterTypeEnum $type): array {
        return array_values(
            array_filter(
                $parameters,
                static fn(ParameterDTO $parameter): bool => $parameter->getType() === $type
            )
        );
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
        $this->commandListRenderer->render(
            $this->getCommands()
        );
    }
}

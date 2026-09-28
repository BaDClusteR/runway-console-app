<?php

declare(strict_types=1);

namespace Runway\Console\Command\ListRenderer;

use Runway\Console\Command\ICommand;
use Runway\Console\Output\IOutput;

class CommandListRenderer implements ICommandListRenderer {
    /**
     * @param string $indent Indentation of the commands (see console_commands_list_indent parameter).
     */
    public function __construct(
        protected IOutput $output,
        protected string  $indent = '  '
    ) {}

    public function render(array $commands): void {
        if ($commands === []) {
            $this->output->writeln('No commands registered.');
            return;
        }

        // The same width for all groups, so the descriptions are aligned in the whole list.
        $nameWidth = max(
            array_map(
                static fn(ICommand $command): int => mb_strwidth($command->getName()),
                $commands
            )
        );

        $this->output->writeln('');
        $this->output->writeln('<fg=green>Available commands:</>');
        $this->output->writeln('');

        $isFirstBlock = true;

        foreach ($this->groupCommands($commands) as $group => $groupCommands) {
            $group = (string)$group;

            if ($group !== '') {
                if (!$isFirstBlock) {
                    $this->output->writeln('');
                }

                $this->output->writeln('<fg=yellow>' . $this->output->escape($group) . '</>');
            }

            foreach ($groupCommands as $command) {
                $this->renderCommand($command, $nameWidth);
            }

            $isFirstBlock = false;
        }

        $this->output->writeln('');
        $this->output->writeln('Run "help \\<command>" or "\\<command> --help" to see the command usage.');
        $this->output->writeln('');
    }

    protected function renderCommand(ICommand $command, int $nameWidth): void {
        $name = $command->getName();
        $padding = str_repeat(' ', $nameWidth - mb_strwidth($name) + 2);

        $this->output->writeln(
            $this->indent
            . '<fg=cyan>' . $this->output->escape($name) . '</>'
            . $padding
            . $this->output->escape($command->getDescription())
        );
    }

    /**
     * @param ICommand[] $commands
     *
     * @return array<string, ICommand[]> Group name => commands sorted by name. Ungrouped commands go first, under
     *                                   the empty group name.
     */
    protected function groupCommands(array $commands): array {
        $groups = [];

        foreach ($commands as $command) {
            $groups[(string)$command->getGroup()][] = $command;
        }

        // The empty group name is always the first one.
        ksort($groups, SORT_STRING);

        foreach ($groups as &$groupCommands) {
            usort(
                $groupCommands,
                static fn(ICommand $a, ICommand $b): int => strcmp($a->getName(), $b->getName())
            );
        }

        unset($groupCommands);

        return $groups;
    }
}

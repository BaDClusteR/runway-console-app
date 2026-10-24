<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Integration\Fixture\Command;

use Runway\Console\Command\ACommand;
use Runway\Console\Exception\InvalidAnswerException;
use Runway\Console\Exception\InvalidParameterException;
use Runway\Console\Input\IInput;
use Runway\Console\Output\IOutput;
use Runway\Console\Parameter\Enum\ParameterModeEnum;

class PromptCommand extends ACommand {
    public function getName(): string {
        return 'demo:prompt';
    }

    public function getDescription(): string {
        return 'Asks a question and prints the answer';
    }

    public function getGroup(): ?string {
        return 'Demo';
    }

    protected function configure(): void {
        $this
            ->addArgument('type', description: 'confirm, choice, ask, secret or slow-secret')
            ->addOption('default', mode: ParameterModeEnum::VALUE_REQUIRED, description: 'The default answer')
            ->addOption('pid', description: 'Print the process ID first (e.g. to send a signal)');
    }

    protected function execute(IInput $input, IOutput $output): int {
        $default = $input->getOption('default');

        if ($input->getOption('pid')) {
            $output->writeln('PID: ' . getmypid());
        }

        $attempts = 0;

        $answer = match ($input->getArgument('type')) {
            'confirm'     => $output->confirm('Continue?', $default === 'yes'),
            'choice'      => $output->choice('Fruit?', ['apple' => 'Apple', 'banana' => 'Banana', 'cherry' => 'Cherry'], $default),
            'ask'         => $output->ask('Name?', static function (string $answer): string {
                if ($answer === '') {
                    throw new InvalidAnswerException('The name is required.');
                }

                return $answer;
            }, $default),
            'secret'      => $output->secret('Password?'),
            // E.g. the password is checked by an API: the first attempt is wrong.
            'slow-secret' => $output->secret('Password?', static function (string $answer) use (&$attempts): string {
                usleep(700000);

                return (++$attempts > 1) ? $answer : throw new InvalidAnswerException('Wrong password.');
            }),
            default       => throw new InvalidParameterException('Unknown prompt type.'),
        };

        $output->writeln('Answer: ' . $output->escape(var_export($answer, true)));

        return 0;
    }
}

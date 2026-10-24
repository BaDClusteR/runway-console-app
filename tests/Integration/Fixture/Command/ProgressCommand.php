<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Integration\Fixture\Command;

use Runway\Console\Command\ACommand;
use Runway\Console\Input\IInput;
use Runway\Console\Output\IOutput;

class ProgressCommand extends ACommand {
    /**
     * The messages get shorter, so the tail of a previous one would be visible if the line is not cleared.
     *
     * @var list<string>
     */
    protected const array MESSAGES = [
        'Downloading a file with a very long name, so the line is wider than a narrow terminal',
        'Unpacking the archive',
        'Done',
    ];

    public function getName(): string {
        return 'demo:progress';
    }

    public function getDescription(): string {
        return 'Shows a progress bar';
    }

    public function getGroup(): ?string {
        return 'Demo';
    }

    protected function execute(IInput $input, IOutput $output): int {
        $bar = $output->createProgressBar(count(static::MESSAGES));
        $bar->start();

        foreach (static::MESSAGES as $message) {
            $bar->setMessage($message);
            $bar->advance();
        }

        $bar->finish();

        return 0;
    }
}

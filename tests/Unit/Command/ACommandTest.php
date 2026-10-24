<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Runway\Console\Command\ACommand;
use Runway\Console\Input\IInput;
use Runway\Console\Input\Input;
use Runway\Console\Output\IOutput;
use Runway\Console\Parameter\Enum\ParameterModeEnum;
use Runway\Console\Parameter\Enum\ParameterTypeEnum;
use Runway\Console\Tests\Support\BufferedOutput;

class ACommandTest extends TestCase {
    protected function createCommand(): ACommand {
        return new class extends ACommand {
            public int $configureCalls = 0;

            public function getName(): string {
                return 'test';
            }

            public function getDescription(): string {
                return 'Test command';
            }

            protected function configure(): void {
                $this->configureCalls++;

                $this
                    ->addArgument('name', description: 'Name')
                    ->addArgument('title', ParameterModeEnum::OPTIONAL, default: 'Mr.')
                    ->addOption('force', 'f', description: 'Force')
                    ->addOption('level', mode: ParameterModeEnum::VALUE_REQUIRED, default: '1');
            }

            protected function execute(IInput $input, IOutput $output): int {
                $output->writeln('Hello, ' . $input->getArgument('name'));

                return 7;
            }
        };
    }

    public function testParameters(): void {
        $parameters = $this->createCommand()->getParameters();

        self::assertCount(4, $parameters);

        [$name, $title, $force, $level] = $parameters;

        self::assertSame(
            ['name', ParameterTypeEnum::ARGUMENT, ParameterModeEnum::REQUIRED, 'Name', null, null],
            [$name->getName(), $name->getType(), $name->getMode(), $name->getDescription(), $name->getDefault(), $name->getShortcut()]
        );
        self::assertSame([ParameterModeEnum::OPTIONAL, 'Mr.'], [$title->getMode(), $title->getDefault()]);
        self::assertSame(
            ['force', ParameterTypeEnum::OPTION, ParameterModeEnum::VALUE_NONE, 'f'],
            [$force->getName(), $force->getType(), $force->getMode(), $force->getShortcut()]
        );
        self::assertSame([ParameterModeEnum::VALUE_REQUIRED, '1', null], [$level->getMode(), $level->getDefault(), $level->getShortcut()]);
    }

    public function testConfiguredLazilyAndOnce(): void {
        $command = $this->createCommand();

        self::assertSame(0, $command->configureCalls);

        $command->getParameters();
        $command->getParameters();

        self::assertSame(1, $command->configureCalls);
    }

    public function testNoGroupByDefault(): void {
        self::assertNull($this->createCommand()->getGroup());
    }

    public function testRunExecutes(): void {
        $output = new BufferedOutput();

        self::assertSame(7, $this->createCommand()->run(new Input(['name' => 'John']), $output));
        self::assertSame("Hello, John\n", $output->stdout);
    }
}

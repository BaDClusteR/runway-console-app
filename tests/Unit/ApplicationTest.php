<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Unit;

use LogicException;
use PHPUnit\Framework\TestCase;
use Runway\Console\Application;
use Runway\Console\Command\ICommand;
use Runway\Console\Command\ListRenderer\CommandListRenderer;
use Runway\Console\Exception\ConsoleException;
use Runway\Console\Input\IInput;
use Runway\Console\Input\Parser\InputParser;
use Runway\Console\Output\IOutput;
use Runway\Console\Parameter\Enum\ParameterModeEnum;
use Runway\Console\Tests\Support\BufferedOutput;
use Runway\Console\Tests\Support\CallbackCommand;
use Runway\Env\Provider\IEnvVariablesProvider;
use Runway\Event\IEventDispatcher;
use RuntimeException;

class ApplicationTest extends TestCase {
    protected BufferedOutput $output;

    protected CallbackCommand $greet;

    /** @var array<string, mixed> */
    protected array $env = [];

    /** @var string[]|null */
    protected ?array $originalArgv;

    protected function setUp(): void {
        $this->originalArgv = $_SERVER['argv'] ?? null;
        $this->output = new BufferedOutput();

        $this->greet = new CallbackCommand(
            'greet',
            'Greets someone',
            handler: static function (IInput $input, IOutput $output): int {
                $text = "{$input->getOption('greeting')}, {$input->getArgument('title')} {$input->getArgument('name')}!";
                $output->writeln($input->getOption('shout') ? strtoupper($text) : $text);

                return 0;
            }
        );
        $this->greet
            ->withArgument('name', description: 'Who to greet')
            ->withArgument('title', ParameterModeEnum::OPTIONAL, 'Title', 'friend')
            ->withOption('shout', 's', description: 'Uppercase')
            ->withOption('greeting', mode: ParameterModeEnum::VALUE_REQUIRED, description: 'Greeting', default: 'Hello')
            ->withOption('color', mode: ParameterModeEnum::VALUE_OPTIONAL, description: 'Color');
    }

    protected function tearDown(): void {
        if ($this->originalArgv === null) {
            unset($_SERVER['argv']);
        } else {
            $_SERVER['argv'] = $this->originalArgv;
        }
    }

    /**
     * @param ICommand[] $commands
     */
    protected function createApplication(array $commands, ?IEventDispatcher $eventDispatcher = null): Application {
        $envProvider = $this->createStub(IEnvVariablesProvider::class);
        $envProvider->method('getEnvVariable')->willReturnCallback(fn(string $name): mixed => $this->env[$name] ?? null);

        $application = new class(
            $eventDispatcher ?? $this->createStub(IEventDispatcher::class),
            new InputParser(),
            $this->output,
            $envProvider,
            new CommandListRenderer($this->output)
        ) extends Application {
            /** @var ICommand[] */
            public array $commands = [];

            protected function getCommands(): array {
                return $this->commands;
            }
        };

        $application->commands = $commands;

        return $application;
    }

    /**
     * @param string[]   $arguments The arguments after the script name.
     * @param ICommand[] $commands
     */
    protected function runApp(array $arguments, ?array $commands = null): int {
        $_SERVER['argv'] = ['console', ...$arguments];

        return $this->createApplication($commands ?? [$this->greet])->run();
    }

    public function testKernelIsInitialized(): void {
        $eventDispatcher = $this->createMock(IEventDispatcher::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->with('kernel.init', null);

        $_SERVER['argv'] = ['console', 'greet', 'John'];
        $this->createApplication([$this->greet], $eventDispatcher)->run();
    }

    public function testListWithoutCommandName(): void {
        self::assertSame(0, $this->runApp([]));
        self::assertStringContainsString("Available commands:\n\n  greet  Greets someone\n", $this->output->stdout);
    }

    public function testListCommand(): void {
        self::assertSame(0, $this->runApp(['list']));
        self::assertStringContainsString('  greet  Greets someone', $this->output->stdout);
    }

    public function testRunsCommandWithParsedInput(): void {
        self::assertSame(0, $this->runApp(['greet', 'John', '--shout', '--greeting', 'Hi']));
        self::assertSame("HI, FRIEND JOHN!\n", $this->output->stdout);
        self::assertSame('', $this->output->stderr);
        self::assertCount(1, $this->greet->runs);
    }

    public function testCommandExitCodeIsReturned(): void {
        $command = new CallbackCommand('fail', handler: static fn(): int => 3);

        self::assertSame(3, $this->runApp(['fail'], [$command]));
    }

    public function testUnknownCommand(): void {
        self::assertSame(1, $this->runApp(['unknown']));
        self::assertSame("Command \"unknown\" not found.\n", $this->output->stderr);
        self::assertStringContainsString('Available commands:', $this->output->stdout);
    }

    public function testInvalidInput(): void {
        self::assertSame(1, $this->runApp(['greet', 'John', '--unknown']));
        self::assertSame("Unknown option: --unknown\n", $this->output->stderr);
        self::assertSame('', $this->output->stdout);
        self::assertSame([], $this->greet->runs);
    }

    public function testCommandHelp(): void {
        $expected = implode("\n", [
            '',
            'Greets someone',
            '',
            'Usage:',
            '  greet [options] <name> [<title>]',
            '',
            'Arguments:',
            '  name   Who to greet',
            '  title  Title [default: "friend"]',
            '',
            'Options:',
            '  -s, --shout           Uppercase',
            '      --greeting=VALUE  Greeting [default: "Hello"]',
            '      --color[=VALUE]   Color',
            '',
        ]) . "\n";

        foreach ([['help', 'greet'], ['greet', '--help'], ['greet', 'John', '-h']] as $arguments) {
            $this->output->stdout = '';

            self::assertSame(0, $this->runApp($arguments), implode(' ', $arguments));
            self::assertSame($expected, $this->output->stdout, implode(' ', $arguments));
        }

        self::assertSame([], $this->greet->runs, 'The command is not run for the help.');
    }

    public function testHelpForCommandWithoutParameters(): void {
        $command = new CallbackCommand('about', 'About the app');

        self::assertSame(0, $this->runApp(['help', 'about'], [$command]));
        self::assertSame("\nAbout the app\n\nUsage:\n  about\n\n", $this->output->stdout);
    }

    public function testHelpWithoutCommandName(): void {
        self::assertSame(0, $this->runApp(['help']));
        self::assertStringContainsString('Available commands:', $this->output->stdout);
    }

    public function testHelpForUnknownCommand(): void {
        self::assertSame(1, $this->runApp(['help', 'unknown']));
        self::assertSame("Command \"unknown\" not found.\n", $this->output->stderr);
    }

    public function testErrorWithoutDebugMode(): void {
        $command = new CallbackCommand(
            'fail',
            handler: static fn(): never => throw new RuntimeException('Something <fg=red>broke</>')
        );

        self::assertSame(1, $this->runApp(['fail'], [$command]));
        // The message is output as is, not formatted.
        self::assertSame("Something <fg=red>broke</>\n", $this->output->stderr);
    }

    public function testErrorTraceInDebugMode(): void {
        $this->env['APP_DEBUG'] = true;
        $command = new CallbackCommand(
            'fail',
            handler: static fn(): never => throw new RuntimeException('Outer', 0, new LogicException('Inner'))
        );

        self::assertSame(1, $this->runApp(['fail'], [$command]));

        $stderr = $this->output->stderr;

        self::assertStringStartsWith("Outer\nRuntimeException at " . __FILE__ . ':', $stderr);
        self::assertStringContainsString("\nCaused by: LogicException at " . __FILE__ . ':', $stderr);
        self::assertStringContainsString('#0 ', $stderr);
    }

    public function testNoTraceForConsoleExceptionsInDebugMode(): void {
        $this->env['APP_DEBUG'] = true;
        $command = new CallbackCommand('fail', handler: static fn(): never => throw new ConsoleException('Bad input'));

        self::assertSame(1, $this->runApp(['fail'], [$command]));
        self::assertSame("Bad input\n", $this->output->stderr);
    }

    public function testDebugModeIsOnlyEnabledByTrue(): void {
        $this->env['APP_DEBUG'] = 'true';
        $command = new CallbackCommand('fail', handler: static fn(): never => throw new RuntimeException('Failed'));

        self::assertSame(1, $this->runApp(['fail'], [$command]));
        self::assertSame("Failed\n", $this->output->stderr);
    }
}

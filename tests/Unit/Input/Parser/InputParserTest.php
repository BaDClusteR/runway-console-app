<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Unit\Input\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Console\Exception\InvalidParameterException;
use Runway\Console\Input\Parser\InputParser;
use Runway\Console\Parameter\DTO\ParameterDTO;
use Runway\Console\Parameter\Enum\ParameterModeEnum;
use Runway\Console\Parameter\Enum\ParameterTypeEnum;

class InputParserTest extends TestCase {
    protected InputParser $parser;

    protected function setUp(): void {
        $this->parser = new InputParser();
    }

    /**
     * Arguments: source (required), target (optional, "out" by default).
     * Options: --force/-f (flag), --level/-l (value required, "1" by default), --color/-c (value optional),
     * --verbose/-v (flag), --name (value required, no shortcut).
     *
     * @return ParameterDTO[]
     */
    protected static function getParameters(): array {
        return [
            new ParameterDTO('source', ParameterTypeEnum::ARGUMENT, ParameterModeEnum::REQUIRED),
            new ParameterDTO('target', ParameterTypeEnum::ARGUMENT, ParameterModeEnum::OPTIONAL, default: 'out'),
            new ParameterDTO('force', ParameterTypeEnum::OPTION, ParameterModeEnum::VALUE_NONE, shortcut: 'f'),
            new ParameterDTO(
                'level', ParameterTypeEnum::OPTION, ParameterModeEnum::VALUE_REQUIRED, default: '1', shortcut: 'l'
            ),
            new ParameterDTO('color', ParameterTypeEnum::OPTION, ParameterModeEnum::VALUE_OPTIONAL, shortcut: 'c'),
            new ParameterDTO('verbose', ParameterTypeEnum::OPTION, ParameterModeEnum::VALUE_NONE, shortcut: 'v'),
            new ParameterDTO('name', ParameterTypeEnum::OPTION, ParameterModeEnum::VALUE_REQUIRED),
        ];
    }

    /**
     * @param string[] $tokens
     *
     * @return array{arguments: array<string, string|null>, options: array<string, string|null>}
     */
    protected function parse(array $tokens): array {
        $input = $this->parser->parse($tokens, static::getParameters());

        return ['arguments' => $input->getArguments(), 'options' => $input->getOptions()];
    }

    public function testDefaults(): void {
        self::assertSame(
            [
                'arguments' => ['source' => 'src', 'target' => 'out'],
                'options'   => ['force' => null, 'level' => '1', 'color' => null, 'verbose' => null, 'name' => null],
            ],
            $this->parse(['src'])
        );
    }

    public function testAllArguments(): void {
        self::assertSame(['source' => 'a', 'target' => 'b'], $this->parse(['a', 'b'])['arguments']);
    }

    /**
     * @return iterable<string, array{string[], array<string, string|null>}>
     */
    public static function optionsProvider(): iterable {
        yield 'long flag' => [['--force'], ['force' => '1']];
        yield 'short flag' => [['-f'], ['force' => '1']];
        yield 'flags cluster' => [['-fv'], ['force' => '1', 'verbose' => '1']];
        yield 'long value with "="' => [['--level=3'], ['level' => '3']];
        yield 'long value as the next token' => [['--level', '3'], ['level' => '3']];
        yield 'short value as the next token' => [['-l', '3'], ['level' => '3']];
        yield 'value with "=" inside' => [['--name=a=b'], ['name' => 'a=b']];
        yield 'empty value with "="' => [['--name='], ['name' => '']];
        yield 'negative number as a value' => [['--level', '-5'], ['level' => '-5']];
        yield 'negative float as a value' => [['-l', '-0.5'], ['level' => '-0.5']];
        yield 'optional value given' => [['--color', 'red'], ['color' => 'red']];
        yield 'optional value with "="' => [['--color=red'], ['color' => 'red']];
        // Must differ from the option not given (null) and from the value "1".
        yield 'optional value not given' => [['--color'], ['color' => '']];
        yield 'optional value not given, short' => [['-c'], ['color' => '']];
        yield 'optional value before another option' => [['--color', '--force'], ['color' => '', 'force' => '1']];
        yield 'optional value in a cluster' => [['-vc'], ['verbose' => '1', 'color' => '']];
        yield 'the last one wins' => [['--level=2', '--level=3'], ['level' => '3']];
    }

    /**
     * @param string[]                   $tokens
     * @param array<string, string|null> $expectedOptions Only the options to check.
     */
    #[DataProvider('optionsProvider')]
    public function testOptions(array $tokens, array $expectedOptions): void {
        $options = $this->parse(['src', ...$tokens])['options'];

        self::assertSame($expectedOptions, array_intersect_key($options, $expectedOptions));
    }

    public function testOptionsAndArgumentsInAnyOrder(): void {
        self::assertSame(
            [
                'arguments' => ['source' => 'a', 'target' => 'b'],
                'options'   => ['force' => '1', 'level' => '5', 'color' => null, 'verbose' => null, 'name' => null],
            ],
            $this->parse(['--force', 'a', '-l', '5', 'b'])
        );
    }

    public function testDoubleDashEndsOptions(): void {
        $result = $this->parse(['--force', '--', '--level', '-v']);

        self::assertSame(['source' => '--level', 'target' => '-v'], $result['arguments']);
        self::assertSame('1', $result['options']['force']);
        self::assertSame('1', $result['options']['level']);
    }

    public function testNegativeNumbersAreArguments(): void {
        self::assertSame(['source' => '-5', 'target' => '-0.5'], $this->parse(['-5', '-0.5'])['arguments']);
        self::assertSame(['source' => '-1e3', 'target' => 'out'], $this->parse(['-f', '-1e3'])['arguments']);
    }

    public function testOptionWithNumericShortcutIsNotNegativeNumber(): void {
        $input = $this->parser->parse(['-1', '-2'], [
            new ParameterDTO('one', ParameterTypeEnum::OPTION, ParameterModeEnum::VALUE_NONE, shortcut: '1'),
            new ParameterDTO('number', ParameterTypeEnum::ARGUMENT, ParameterModeEnum::REQUIRED),
        ]);

        self::assertSame(['one' => '1'], $input->getOptions());
        self::assertSame(['number' => '-2'], $input->getArguments());
    }

    public function testSingleDashIsArgument(): void {
        // Usually means STDIN.
        self::assertSame(['source' => '-', 'target' => 'out'], $this->parse(['-'])['arguments']);
    }

    /**
     * @return iterable<string, array{string[], string}>
     */
    public static function invalidInputProvider(): iterable {
        yield 'missing required argument' => [[], 'Missing required argument: source'];
        yield 'too many arguments' => [['a', 'b', 'c'], 'Too many arguments: expected at most 2, got 3'];
        yield 'unknown long option' => [['src', '--unknown'], 'Unknown option: --unknown'];
        yield 'unknown long option with value' => [['src', '--unknown=1'], 'Unknown option: --unknown'];
        yield 'unknown short option' => [['src', '-x'], 'Unknown option: -x'];
        yield 'unknown option in a cluster' => [['src', '-fx'], 'Unknown option: -x'];
        yield 'value for a flag' => [['src', '--force=1'], 'Option --force does not accept a value.'];
        yield 'missing value at the end' => [['src', '--level'], 'Option --level requires a value.'];
        yield 'missing value before an option' => [['src', '--level', '--force'], 'Option --level requires a value.'];
        yield 'missing short value' => [['src', '-l'], 'Option -l requires a value.'];
        yield 'value option in a cluster' => [['src', '-fl'], 'Option -l requires a value and cannot be combined.'];
    }

    /**
     * @param string[] $tokens
     */
    #[DataProvider('invalidInputProvider')]
    public function testInvalidInput(array $tokens, string $expectedMessage): void {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->parse($tokens);
    }

    public function testNoParameters(): void {
        $input = $this->parser->parse([], []);

        self::assertSame([], $input->getArguments());
        self::assertSame([], $input->getOptions());
    }
}

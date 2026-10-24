<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Unit\Input;

use PHPUnit\Framework\TestCase;
use Runway\Console\Input\Input;

class InputTest extends TestCase {
    public function testValues(): void {
        $input = new Input(['name' => 'John'], ['force' => '1']);

        self::assertSame('John', $input->getArgument('name'));
        self::assertSame('1', $input->getOption('force'));
        self::assertSame(['name' => 'John'], $input->getArguments());
        self::assertSame(['force' => '1'], $input->getOptions());
    }

    public function testDefinedParameterWithoutValueDiffersFromUndefinedOne(): void {
        $input = new Input(['title' => null], ['level' => null]);

        self::assertTrue($input->hasArgument('title'));
        self::assertNull($input->getArgument('title'));
        self::assertTrue($input->hasOption('level'));
        self::assertNull($input->getOption('level'));

        self::assertFalse($input->hasArgument('unknown'));
        self::assertNull($input->getArgument('unknown'));
        self::assertFalse($input->hasOption('unknown'));
        self::assertNull($input->getOption('unknown'));
    }
}

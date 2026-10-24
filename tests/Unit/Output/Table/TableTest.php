<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Unit\Output\Table;

use PHPUnit\Framework\TestCase;
use Runway\Console\Output\Formatter\OutputFormatter;
use Runway\Console\Output\Table\Table;

class TableTest extends TestCase {
    protected OutputFormatter $formatter;

    protected Table $table;

    protected function setUp(): void {
        $this->formatter = new OutputFormatter();
        $this->table = new Table($this->formatter);
    }

    /**
     * @param string[] $lines
     */
    protected static function lines(array $lines): string {
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    public function testRender(): void {
        self::assertSame(
            static::lines([
                '┌────┬───────┐',
                '│ ID │ Name  │',
                '├────┼───────┤',
                '│ 1  │ Alice │',
                '│ 22 │ Bob   │',
                '└────┴───────┘',
            ]),
            $this->table->render(['ID', 'Name'], [['1', 'Alice'], ['22', 'Bob']])
        );
    }

    public function testWithoutRows(): void {
        self::assertSame(
            static::lines([
                '┌──────┬────────┐',
                '│ Only │ Header │',
                '└──────┴────────┘',
            ]),
            $this->table->render(['Only', 'Header'], [])
        );
    }

    public function testWideCharacters(): void {
        // CJK characters and most emoji take two columns.
        self::assertSame(
            static::lines([
                '┌──────────┬────┐',
                '│ Name     │ OK │',
                '├──────────┼────┤',
                '│ 世界     │ 🎉 │',
                '│ Привет   │ ✓  │',
                '│ 漢字仮名 │    │',
                '└──────────┴────┘',
            ]),
            $this->table->render(['Name', 'OK'], [['世界', '🎉'], ['Привет', '✓'], ['漢字仮名', '']])
        );
    }

    public function testFormattingIsKeptAndDoesNotWidenColumns(): void {
        $output = $this->table->render(
            ['<options=bold>Status</>'],
            [['<fg=green>ok</>'], ['<fg=black;bg=red>failed</>'], [$this->formatter->escape('<tag>')]]
        );

        self::assertSame(
            static::lines([
                '┌────────┐',
                '│ Status │',
                '├────────┤',
                '│ ok     │',
                '│ failed │',
                '│ <tag>  │',
                '└────────┘',
            ]),
            $this->formatter->format($output, false)
        );
        self::assertStringContainsString('│ <fg=green>ok</>     │', $output);
    }

    public function testLineBreaksAreReplaced(): void {
        self::assertSame(
            static::lines([
                '┌──────────────┐',
                '│ Text         │',
                '├──────────────┤',
                '│ one two  thr │',
                '└──────────────┘',
            ]),
            $this->table->render(['Text'], [["one\ntwo\r\n\rthr"]])
        );
    }

    public function testMissingAndExtraCells(): void {
        self::assertSame(
            static::lines([
                '┌───┬───┬───┐',
                '│ A │ B │   │',
                '├───┼───┼───┤',
                '│ 1 │   │   │',
                '│ 1 │ 2 │ 3 │',
                '└───┴───┴───┘',
            ]),
            $this->table->render(['A', 'B'], [['1'], ['1', '2', '3']])
        );
    }

    public function testNonStringCells(): void {
        self::assertSame(
            static::lines([
                '┌───┬─────┬───┐',
                '│ A │ B   │ C │',
                '├───┼─────┼───┤',
                '│ 1 │ 2.5 │   │',
                '└───┴─────┴───┘',
            ]),
            $this->table->render(['A', 'B', 'C'], [[1, 2.5, null]])
        );
    }
}

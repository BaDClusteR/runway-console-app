<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Unit\Output\Formatter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Console\Output\Formatter\OutputFormatter;

class OutputFormatterTest extends TestCase {
    protected OutputFormatter $formatter;

    protected function setUp(): void {
        $this->formatter = new OutputFormatter();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function decoratedProvider(): iterable {
        yield 'foreground' => ['<fg=green>ok</>', "\033[32mok\033[0m"];
        yield 'background' => ['<bg=red>!</>', "\033[41m!\033[0m"];
        yield 'foreground and background' => ['<fg=black;bg=green>ok</>', "\033[30;42mok\033[0m"];
        yield 'options' => ['<options=bold,underscore>b</>', "\033[1;4mb\033[0m"];
        yield 'all together' => ['<options=dim;fg=cyan;bg=default>x</>', "\033[2;36;49mx\033[0m"];
        yield 'unknown values are skipped' => ['<fg=pink;options=blink,bold>x</>', "\033[1mx\033[0m"];
        yield 'a tag without known values is removed' => ['<fg=pink>x</>', "x\033[0m"];
        yield 'nested tags' => ['<fg=red>a <options=bold>b</> c</>', "\033[31ma \033[1mb\033[0m c\033[0m"];
        yield 'not a tag' => ['a <b> c <fg=RED>d', 'a <b> c <fg=RED>d'];
        yield 'colors are not options' => ['<options=red;bg=bold>x</>', "x\033[0m"];
        yield 'an incomplete tag' => ['<fg=red', '<fg=red'];
        yield 'no tags' => ['plain text', 'plain text'];
    }

    #[DataProvider('decoratedProvider')]
    public function testFormatDecorated(string $message, string $expected): void {
        self::assertSame($expected, $this->formatter->format($message));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function undecoratedProvider(): iterable {
        yield 'tags are removed' => ['<fg=black;bg=green> ok </> done', ' ok  done'];
        yield 'options are removed' => ['<options=bold>b</>', 'b'];
        yield 'not a tag is kept' => ['a <b> c', 'a <b> c'];
        yield 'escaped tag is output as is' => ['\\<fg=red>x\\</>', '<fg=red>x</>'];
    }

    #[DataProvider('undecoratedProvider')]
    public function testFormatUndecorated(string $message, string $expected): void {
        self::assertSame($expected, $this->formatter->format($message, false));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function userTextProvider(): iterable {
        yield 'a tag' => ['<fg=red>not red</>'];
        yield 'a reset tag' => ['before</>after'];
        yield 'a backslash before a tag' => ['C:\\<fg=red>dir'];
        yield 'an already escaped tag' => ['\\<options=bold>'];
        yield 'html' => ['<div class="a">&lt;</div>'];
        yield 'unicode' => ['Привет, 世界 🎉'];
        yield 'tabs and line breaks' => ["a\tb\nc"];
    }

    /**
     * Whatever the user data is, the escaped text is output exactly as it was.
     */
    #[DataProvider('userTextProvider')]
    public function testEscapedTextIsOutputAsIs(string $text): void {
        $escaped = $this->formatter->escape($text);

        self::assertSame($text, $this->formatter->format($escaped));
        self::assertSame($text, $this->formatter->format($escaped, false));
    }

    public function testEscapedTextCanBeWrappedInTags(): void {
        $message = '<fg=red>' . $this->formatter->escape('<options=bold>') . '</>';

        self::assertSame("\033[31m<options=bold>\033[0m", $this->formatter->format($message));
    }

    public function testEscapeReplacesControlCharacters(): void {
        // An escape sequence in the user data could change the terminal state, e.g. the colors or the title.
        self::assertSame(
            "\u{FFFD}[31mred\u{FFFD}]0;title\u{FFFD}",
            $this->formatter->escape("\033[31mred\033]0;title\x07")
        );
    }

    public function testEscapeReplacesC1ControlCharacters(): void {
        // U+009B is CSI ("\033[") for some terminals; U+0085 is NEL (a line break).
        self::assertSame("\u{FFFD}2J\u{FFFD}x\u{00A0}é", $this->formatter->escape("\u{009B}2J\u{0085}x\u{00A0}é"));
    }

    public function testEscapeReplacesInvalidUtf8(): void {
        $substituteCharacter = mb_substitute_character();

        // A raw 0x9B byte is CSI in the 8-bit mode.
        self::assertSame("a\u{FFFD}2Jb\u{FFFD}Ж", $this->formatter->escape("a\x9B2Jb\xFFЖ"));
        // The global setting is not changed.
        self::assertSame($substituteCharacter, mb_substitute_character());
    }

    public function testEscapeKeepsTabsAndLineBreaks(): void {
        self::assertSame("a\tb\nc\nd", $this->formatter->escape("a\tb\nc\r\nd"));
    }

    public function testEscapeReplacesCarriageReturn(): void {
        // "\r" alone would return the cursor and let the text overwrite the line.
        self::assertSame("progress\u{FFFD}fake", $this->formatter->escape("progress\rfake"));
    }
}

<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Unit\Prompt;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Console\Exception\ConsoleException;
use Runway\Console\Exception\InvalidAnswerException;
use Runway\Console\Exception\PromptCancelledException;
use Runway\Console\Tests\Support\TestPrompt;
use Runway\Console\Tests\Support\VirtualScreen;

class PromptTest extends TestCase {
    protected const string ENTER = "\r";

    protected const string ESC = "\033";

    protected const string CTRL_C = "\x03";

    protected const string CTRL_D = "\x04";

    protected const string UP = "\033[A";

    protected const string DOWN = "\033[B";

    protected const string RIGHT = "\033[C";

    protected const string LEFT = "\033[D";

    protected const string TAB = "\t";

    protected const string BACKSPACE = "\x7f";

    protected const string CTRL_U = "\x15";

    protected const string RAW_MODE = '-icanon -echo -isig min 1';

    /** @var non-empty-string */
    protected const string HIDE_CURSOR = "\033[?25l";

    /** @var non-empty-string */
    protected const string SHOW_CURSOR = "\033[?25h";

    /**
     * The terminal is switched to the raw mode and then restored, the cursor is hidden and then shown again.
     */
    protected static function assertTerminalRestored(TestPrompt $prompt): void {
        self::assertSame([static::RAW_MODE, escapeshellarg('saved-state')], $prompt->sttyCalls);
        $output = $prompt->getOutput();

        self::assertStringStartsWith(static::HIDE_CURSOR, $output);
        self::assertGreaterThan(strrpos($output, static::HIDE_CURSOR), (int)strrpos($output, static::SHOW_CURSOR));
    }

    // Confirm.

    /**
     * @return iterable<string, array{string[], bool, bool}>
     */
    public static function confirmProvider(): iterable {
        yield 'enter, default no' => [[static::ENTER], false, false];
        yield 'enter, default yes' => [[static::ENTER], true, true];
        yield 'right arrow toggles' => [[static::RIGHT, static::ENTER], false, true];
        yield 'left arrow toggles' => [[static::LEFT, static::ENTER], true, false];
        yield 'tab toggles' => [[static::TAB, static::TAB, static::TAB, static::ENTER], true, false];
        yield 'first letter of yes' => [['y'], false, true];
        yield 'first letter of no, uppercase' => [['N'], true, false];
        yield 'other keys are ignored' => [['x', static::UP, '1', static::ENTER], true, true];
    }

    /**
     * @param string[] $keys
     */
    #[DataProvider('confirmProvider')]
    public function testConfirm(array $keys, bool $default, bool $expected): void {
        $prompt = new TestPrompt($keys);

        self::assertSame($expected, $prompt->confirm('Continue?', $default));
        self::assertSame(['? Continue? ' . ($expected ? 'Yes' : 'No')], $prompt->getScreen());
        static::assertTerminalRestored($prompt);
    }

    public function testConfirmButtons(): void {
        $prompt = new TestPrompt([static::RIGHT, static::ENTER]);
        $prompt->confirm('Continue?');

        self::assertSame(
            [['? Continue?  Yes  [No]'], ['? Continue? [Yes]  No ']],
            $prompt->frames
        );
    }

    public function testConfirmCustomLabels(): void {
        $prompt = new TestPrompt(['d']);

        self::assertTrue($prompt->confirm('Remove?', false, 'Delete', 'Keep'));
        self::assertSame(['? Remove? Delete'], $prompt->getScreen());
    }

    public function testConfirmLabelsWithTheSameFirstLetterAreNotChosenByLetter(): void {
        $prompt = new TestPrompt(['s', static::ENTER]);

        self::assertFalse($prompt->confirm('Save?', false, 'Save', 'Skip'));
    }

    public function testConfirmButtonsGoToTheNextLineInNarrowTerminal(): void {
        $prompt = new TestPrompt([static::ENTER], terminalWidth: 30);
        $prompt->confirm('Do you really want to continue?', true);

        self::assertSame([['? Do you really want to continue?', '  [Yes]  No ']], $prompt->frames);
        self::assertSame(['? Do you really want to continue? Yes'], $prompt->getScreen());
    }

    public function testConfirmWithoutTerminalReturnsDefault(): void {
        $prompt = new TestPrompt(isTerminal: false);

        self::assertTrue($prompt->confirm('Continue?', true));
        self::assertFalse($prompt->confirm('Continue?'));
        self::assertSame('', $prompt->getOutput());
    }

    /**
     * @return iterable<string, array{string[], bool, bool}>
     */
    public static function confirmLineProvider(): iterable {
        yield 'empty line is the default' => [["\n"], true, true];
        yield 'y' => [["y\n"], false, true];
        yield 'yes' => [["Yes\n"], false, true];
        yield 'no' => [["no\n"], true, false];
        yield 'asked again on an unknown answer' => [["maybe\n", "n\n"], true, false];
    }

    /**
     * @param string[] $lines
     */
    #[DataProvider('confirmLineProvider')]
    public function testConfirmWithoutRawMode(array $lines, bool $default, bool $expected): void {
        $prompt = new TestPrompt($lines, isRawModeAvailable: false);

        self::assertSame($expected, $prompt->confirm('Continue?', $default));
        self::assertSame([], $prompt->sttyCalls);
        self::assertStringStartsWith('? Continue? (' . ($default ? 'Yes/no' : 'yes/No') . ') ', $prompt->getOutput());
    }

    // Cancelling.

    /**
     * @return iterable<string, array{string[]}>
     */
    public static function cancelProvider(): iterable {
        yield 'esc' => [[static::ESC]];
        yield 'ctrl+c' => [[static::CTRL_C]];
        yield 'ctrl+d' => [[static::CTRL_D]];
        yield 'end of input' => [[]];
        yield 'after moving' => [[static::RIGHT, static::ESC]];
    }

    /**
     * @param string[] $keys
     */
    #[DataProvider('cancelProvider')]
    public function testCancel(array $keys): void {
        $prompt = new TestPrompt($keys);

        try {
            $prompt->confirm('Continue?');
            self::fail('The prompt is not cancelled.');
        } catch (PromptCancelledException $e) {
            self::assertSame('Cancelled.', $e->getMessage());
        }

        // The prompt is removed, and the terminal is restored even on cancel.
        self::assertSame([], $prompt->getScreen());
        self::assertSame([static::RAW_MODE, escapeshellarg('saved-state')], $prompt->sttyCalls);
        self::assertStringEndsWith(static::SHOW_CURSOR, $prompt->getOutput());
    }

    public function testCancelWithoutRawMode(): void {
        $prompt = new TestPrompt([], isRawModeAvailable: false);

        $this->expectException(PromptCancelledException::class);

        $prompt->ask('Name?');
    }

    // Choice.

    /** @var array<string, string> */
    protected const array FRUITS = ['apple' => 'Apple', 'banana' => 'Banana', 'cherry' => 'Cherry'];

    /**
     * @return iterable<string, array{string[], string|null, string}>
     */
    public static function choiceProvider(): iterable {
        yield 'enter, the first one by default' => [[static::ENTER], null, 'apple'];
        yield 'the default one' => [[static::ENTER], 'banana', 'banana'];
        yield 'unknown default is the first one' => [[static::ENTER], 'kiwi', 'apple'];
        yield 'down' => [[static::DOWN, static::DOWN, static::ENTER], null, 'cherry'];
        yield 'down wraps around' => [[static::DOWN, static::ENTER], 'cherry', 'apple'];
        yield 'up wraps around' => [[static::UP, static::ENTER], null, 'cherry'];
        yield 'tab is down' => [[static::TAB, static::ENTER], null, 'banana'];
        yield 'number' => [['3', static::ENTER], null, 'cherry'];
        yield 'number out of range is ignored' => [['4', '0', static::ENTER], 'banana', 'banana'];
    }

    /**
     * @param string[] $keys
     */
    #[DataProvider('choiceProvider')]
    public function testChoice(array $keys, ?string $default, string $expected): void {
        $prompt = new TestPrompt($keys);

        self::assertSame($expected, $prompt->choice('Fruit?', static::FRUITS, $default));
        self::assertSame(['? Fruit? ' . static::FRUITS[$expected]], $prompt->getScreen());
        static::assertTerminalRestored($prompt);
    }

    public function testChoiceList(): void {
        $prompt = new TestPrompt([static::DOWN, static::ENTER]);
        $prompt->choice('Fruit?', static::FRUITS);

        self::assertSame(
            ['? Fruit? (↑/↓, Enter)', '  1. Apple', '❯ 2. Banana', '  3. Cherry'],
            $prompt->getLastFrame()
        );
    }

    public function testChoiceWithIntegerKeys(): void {
        $prompt = new TestPrompt([static::DOWN, static::ENTER]);

        self::assertSame(1, $prompt->choice('Fruit?', ['Apple', 'Banana']));
    }

    public function testLongChoiceListIsScrolled(): void {
        $choices = array_map(static fn(int $i): string => "Item $i", range(1, 15));
        $prompt = new TestPrompt([static::UP, static::ENTER]);

        self::assertSame(14, $prompt->choice('Item?', $choices));

        // The first frame starts at the top, then the list is scrolled to the last item.
        self::assertSame('❯ 1. Item 1', $prompt->frames[0][1]);
        self::assertSame('  (1/15)', end($prompt->frames[0]));
        self::assertSame(
            [
                '? Item? (↑/↓, Enter)',
                '  6. Item 6', '  7. Item 7', '  8. Item 8', '  9. Item 9', '  10. Item 10',
                '  11. Item 11', '  12. Item 12', '  13. Item 13', '  14. Item 14', '❯ 15. Item 15',
                '  (15/15)',
            ],
            $prompt->getLastFrame()
        );
        self::assertSame(['? Item? Item 15'], $prompt->getScreen());
    }

    public function testChoiceWithoutChoices(): void {
        $this->expectException(ConsoleException::class);
        $this->expectExceptionMessage('Nothing to choose from.');

        (new TestPrompt())->choice('Fruit?', []);
    }

    public function testChoiceWithoutTerminal(): void {
        self::assertSame('banana', (new TestPrompt(isTerminal: false))->choice('Fruit?', static::FRUITS, 'banana'));

        $this->expectException(ConsoleException::class);
        $this->expectExceptionMessage('Fruit?: an answer is required, but the terminal is not interactive.');

        (new TestPrompt(isTerminal: false))->choice('Fruit?', static::FRUITS);
    }

    /**
     * @return iterable<string, array{string[], string|null, string}>
     */
    public static function choiceLineProvider(): iterable {
        yield 'number' => [["2\n"], null, 'banana'];
        yield 'empty line is the default' => [["\n"], 'cherry', 'cherry'];
        yield 'asked again on a wrong number' => [["5\n", "x\n", "\n", "1\n"], null, 'apple'];
    }

    /**
     * @param string[] $lines
     */
    #[DataProvider('choiceLineProvider')]
    public function testChoiceWithoutRawMode(array $lines, ?string $default, string $expected): void {
        $prompt = new TestPrompt($lines, isRawModeAvailable: false);

        self::assertSame($expected, $prompt->choice('Fruit?', static::FRUITS, $default));
        self::assertStringStartsWith("? Fruit?\n  1. Apple\n  2. Banana\n  3. Cherry\n  Number", $prompt->getOutput());
    }

    // Ask.

    /**
     * @return iterable<string, array{string[], string|null, string}>
     */
    public static function askProvider(): iterable {
        yield 'typing' => [['J', 'o', 'h', 'n', static::ENTER], null, 'John'];
        yield 'empty answer' => [[static::ENTER], null, ''];
        yield 'default on an empty answer' => [[static::ENTER], 'Bob', 'Bob'];
        yield 'default is not used for a typed answer' => [['A', static::ENTER], 'Bob', 'A'];
        yield 'backspace' => [['J', 'o', 'x', static::BACKSPACE, 'e', static::ENTER], null, 'Joe'];
        yield 'backspace on empty' => [[static::BACKSPACE, 'a', static::ENTER], null, 'a'];
        yield 'backspace removes a whole multibyte character' => [['Жук', static::BACKSPACE, static::ENTER], null, 'Жу'];
        yield 'ctrl+u clears' => [['wrong', static::CTRL_U, 'right', static::ENTER], null, 'right'];
        yield 'arrows are ignored' => [['ab', static::LEFT, static::UP, 'c', static::ENTER], null, 'abc'];
        yield 'paste' => [['hello world', static::ENTER], null, 'hello world'];
        yield 'paste with a line break is answered' => [["first line\nsecond line"], null, 'first line'];
        yield 'paste with a line break after the typed text' => [['a', "b\r\nc"], null, 'ab'];
        yield 'control characters are removed' => [["a\x01b\x1Fc\x00", static::ENTER], null, 'abc'];
        yield 'multibyte character in two reads' => [["\xD0", "\x96", static::ENTER], null, 'Ж'];
        yield 'emoji in parts' => [["\xF0\x9F", "\x8E", "\x89", static::ENTER], null, '🎉'];
        yield 'invalid bytes are dropped' => [["\xFF\xFF\xFF\xFF", 'ok', static::ENTER], null, 'ok'];
    }

    /**
     * @param string[] $keys
     */
    #[DataProvider('askProvider')]
    public function testAsk(array $keys, ?string $default, string $expected): void {
        $prompt = new TestPrompt($keys);

        self::assertSame($expected, $prompt->ask('Name?', default: $default));
        self::assertSame([rtrim('? Name? ' . $expected)], $prompt->getScreen());
        static::assertTerminalRestored($prompt);
    }

    public function testAskInputLine(): void {
        $prompt = new TestPrompt(['J', static::ENTER]);
        $prompt->ask('Name?', default: 'Bob');

        // The default is shown until something is typed.
        self::assertSame([['? Name? (Bob) _'], ['? Name? J_']], $prompt->frames);
    }

    public function testAskAnswerIsShownAsIs(): void {
        $prompt = new TestPrompt(['<fg=red>x</>', static::ENTER]);

        self::assertSame('<fg=red>x</>', $prompt->ask('Tag?'));
        self::assertSame(['? Tag? <fg=red>x</>'], $prompt->getScreen());
    }

    public function testAskShowsTheEndOfLongAnswer(): void {
        $prompt = new TestPrompt([str_repeat('a', 30) . 'END', static::ENTER], terminalWidth: 30);
        $prompt->ask('Name?');

        $line = $prompt->getLastFrame()[0];

        self::assertStringStartsWith('? Name? …', $line);
        self::assertStringEndsWith('aaEND_', $line);
        self::assertLessThan(30, mb_strwidth($line));
    }

    public function testAskInputGoesToTheNextLineInNarrowTerminal(): void {
        $prompt = new TestPrompt(['x', static::ENTER], terminalWidth: 20);
        $prompt->ask('What is your full name?');

        self::assertSame(['? What is your ful…', '❯ x_'], VirtualScreen::render($this->outputBeforeAnswer($prompt)));
    }

    public function testAskValidator(): void {
        $prompt = new TestPrompt(['Jo', static::ENTER, 'John', static::ENTER]);

        $result = $prompt->ask(
            'Name?',
            static function (string $answer): int {
                if (mb_strlen($answer) < 3) {
                    throw new InvalidAnswerException('At least 3 <characters>.');
                }

                return mb_strlen($answer);
            }
        );

        self::assertSame(4, $result);
        self::assertSame(['? Name? Jo', '✖ At least 3 <characters>.', '? Name? John'], $prompt->getScreen());
    }

    public function testAskValidatorIsCalledOutsideOfRawMode(): void {
        $prompt = new TestPrompt(['a', static::ENTER]);
        $calls = [];

        $prompt->ask('Name?', static function () use ($prompt, &$calls): void {
            $calls = $prompt->sttyCalls;
        });

        self::assertSame([static::RAW_MODE, escapeshellarg('saved-state')], $calls);
    }

    public function testAskWithoutTerminal(): void {
        $prompt = new TestPrompt(isTerminal: false);

        self::assertSame('Bob', $prompt->ask('Name?', default: 'Bob'));
        // The default answer is validated too.
        self::assertSame('BOB', $prompt->ask('Name?', strtoupper(...), 'Bob'));

        $this->expectException(ConsoleException::class);
        $this->expectExceptionMessage('Name?: an answer is required, but the terminal is not interactive.');

        $prompt->ask('Name?');
    }

    public function testAskWithoutRawMode(): void {
        $prompt = new TestPrompt(["John\r\n", "\n"], isRawModeAvailable: false);

        self::assertSame('John', $prompt->ask('Name?'));
        self::assertSame('Bob', $prompt->ask('Name?', default: 'Bob'));
        self::assertStringStartsWith('? Name? ', $prompt->getOutput());
        self::assertStringContainsString('? Name? (Bob) ', $prompt->getOutput());
    }

    // Secret.

    public function testSecretIsMasked(): void {
        $prompt = new TestPrompt(['p', 'ä', 's', 's', static::BACKSPACE, static::ENTER]);

        self::assertSame('päs', $prompt->secret('Password?'));
        self::assertSame(['? Password? •_'], $prompt->frames[1]);
        self::assertSame(['? Password? •••_'], $prompt->getLastFrame());
        self::assertSame(['? Password? •••'], $prompt->getScreen());
        self::assertStringNotContainsString('ä', $prompt->getOutput());
        static::assertTerminalRestored($prompt);
    }

    public function testSecretValidator(): void {
        $prompt = new TestPrompt(['123', static::ENTER, 'secret', static::ENTER]);

        $result = $prompt->secret('Password?', static function (string $answer): string {
            if (ctype_digit($answer)) {
                throw new InvalidAnswerException('Too simple.');
            }

            return strrev($answer);
        });

        self::assertSame('terces', $result);
        self::assertSame(['? Password? •••', '✖ Too simple.', '? Password? ••••••'], $prompt->getScreen());
        self::assertStringNotContainsString('secret', $prompt->getOutput());
    }

    public function testSecretWithoutTerminal(): void {
        $this->expectException(ConsoleException::class);
        $this->expectExceptionMessage('Password?: an answer is required, but the terminal is not interactive.');

        (new TestPrompt(isTerminal: false))->secret('Password?');
    }

    public function testSecretWithoutRawMode(): void {
        $prompt = new TestPrompt(["secret\n"], isRawModeAvailable: false);

        $this->expectException(ConsoleException::class);
        $this->expectExceptionMessage('Password?: the input cannot be hidden in this terminal (stty is not available).');

        $prompt->secret('Password?');
    }

    // Layout.

    public function testRedrawingKeepsThePreviousOutput(): void {
        $prompt = new TestPrompt([static::DOWN, static::DOWN, static::ENTER]);
        $prompt->writeOutput("Line 1\nLine 2\n");

        $prompt->choice('Fruit?', static::FRUITS);

        self::assertSame(['Line 1', 'Line 2', '? Fruit? Cherry'], $prompt->getScreen());
    }

    public function testLineThatTakesTheWholeWidthStillFits(): void {
        // "? Continue? [Yes]  No " is 22 columns: the last terminal column is not used.
        $prompt = new TestPrompt([static::ENTER], terminalWidth: 23);
        $prompt->confirm('Continue?', true);
        self::assertSame([['? Continue? [Yes]  No ']], $prompt->frames);

        $prompt = new TestPrompt([static::ENTER], terminalWidth: 22);
        $prompt->confirm('Continue?', true);
        self::assertSame([['? Continue?', '  [Yes]  No ']], $prompt->frames);
    }

    public function testAskInputGoesToTheNextLineIfTheDefaultTakesTheLine(): void {
        // The question alone fits, but not with the default.
        $prompt = new TestPrompt([static::ENTER], terminalWidth: 30);
        $prompt->ask('Name?', default: 'Very long default name');

        self::assertSame(['? Name?', '❯ (Very long default name) _'], $prompt->frames[0]);

        // The question with the default fits, but there is no room to type.
        $prompt = new TestPrompt([static::ENTER], terminalWidth: 40);
        $prompt->ask('Name?', default: 'Very long default name');

        self::assertSame(['? Name?', '❯ (Very long default name) _'], $prompt->frames[0]);
    }

    public function testAskShowsTheWholeAnswerThatFits(): void {
        // 30 columns: "? Name? " and the cursor, the last column is not used.
        $prompt = new TestPrompt([str_repeat('a', 20), static::ENTER], terminalWidth: 30);
        $prompt->ask('Name?');

        self::assertSame(['? Name? ' . str_repeat('a', 20) . '_'], $prompt->getLastFrame());
    }

    public function testAskShowsTheEndOfLongMultibyteAnswer(): void {
        $prompt = new TestPrompt([str_repeat('я', 25) . 'КОНЕЦ', static::ENTER], terminalWidth: 30);
        $prompt->ask('Name?');

        self::assertSame(['? Name? …' . str_repeat('я', 14) . 'КОНЕЦ_'], $prompt->getLastFrame());
    }

    public function testAskShowsTheEndOfLongWideAnswer(): void {
        $prompt = new TestPrompt([str_repeat('世', 20), static::ENTER], terminalWidth: 30);
        $prompt->ask('Name?');

        // 9 wide characters (18 columns) and "…" fit 20 columns.
        self::assertSame(['? Name? …' . str_repeat('世', 9) . '_'], $prompt->getLastFrame());
    }

    public function testChoiceNumberOne(): void {
        $prompt = new TestPrompt(['1', static::ENTER]);

        self::assertSame('apple', $prompt->choice('Fruit?', static::FRUITS, 'banana'));
    }

    public function testConfirmNonAsciiLabels(): void {
        self::assertTrue((new TestPrompt(['Д']))->confirm('Продолжить?', false, 'Да', 'Нет'));
        self::assertFalse((new TestPrompt(['н']))->confirm('Продолжить?', true, 'Да', 'Нет'));
        self::assertFalse((new TestPrompt(['Н']))->confirm('Продолжить?', true, 'Да', 'Нет'));
    }

    public function testConfirmNonAsciiLabelsWithoutRawMode(): void {
        $prompt = new TestPrompt(["ДА\n", "нет\n"], isRawModeAvailable: false);

        self::assertTrue($prompt->confirm('Продолжить?', false, 'Да', 'Нет'));
        self::assertFalse($prompt->confirm('Продолжить?', true, 'Да', 'Нет'));
        self::assertStringStartsWith('? Продолжить? (да/Нет) ', $prompt->getOutput());
        self::assertStringContainsString('? Продолжить? (Да/нет) ', $prompt->getOutput());
    }

    public function testChoiceWithoutRawModeShowsTheDefaultNumber(): void {
        $prompt = new TestPrompt(["\n"], isRawModeAvailable: false);
        $prompt->choice('Fruit?', static::FRUITS, 'cherry');

        self::assertStringEndsWith("\n  Number [3]: ? Fruit? Cherry\n", $prompt->getOutput());

        $prompt = new TestPrompt(["1\n"], isRawModeAvailable: false);
        $prompt->choice('Fruit?', static::FRUITS);

        self::assertStringEndsWith("\n  Number: ? Fruit? Apple\n", $prompt->getOutput());
    }

    public function testChoiceWithoutRawModeAcceptsOnlyItemNumbers(): void {
        $prompt = new TestPrompt(["0\n", "1.5\n", "-1\n", "4\n", "3\n"], isRawModeAvailable: false);

        self::assertSame('cherry', $prompt->choice('Fruit?', static::FRUITS));
        self::assertSame(5, substr_count($prompt->getOutput(), 'Number: '));
    }

    public function testEndOfInputWithoutRawModeEndsTheLine(): void {
        $prompt = new TestPrompt([], isRawModeAvailable: false);

        try {
            $prompt->ask('Name?');
        } catch (PromptCancelledException) {
        }

        self::assertSame("? Name? \n", $prompt->getOutput());
    }

    // Several keys in one read (fast typing, a held key).

    public function testAskKeysInOneRead(): void {
        $prompt = new TestPrompt(["Joxx\x7f\x7fe\r"]);

        self::assertSame('Joe', $prompt->ask('Name?'));
    }

    public function testChoiceKeysInOneRead(): void {
        $prompt = new TestPrompt([static::DOWN . static::DOWN . static::ENTER]);

        self::assertSame('cherry', $prompt->choice('Fruit?', static::FRUITS));
    }

    public function testConfirmKeysInOneRead(): void {
        $prompt = new TestPrompt([static::RIGHT . static::TAB . static::RIGHT . static::ENTER]);

        self::assertTrue($prompt->confirm('Continue?'));
    }

    public function testCancelKeyInOneReadWithOthers(): void {
        $prompt = new TestPrompt(['ab' . static::CTRL_C . 'c' . static::ENTER]);

        $this->expectException(PromptCancelledException::class);

        $prompt->ask('Name?');
    }

    public function testEscFollowedByEsc(): void {
        $prompt = new TestPrompt([static::ESC . static::ESC]);

        $this->expectException(PromptCancelledException::class);

        $prompt->ask('Name?');
    }

    public function testKeysAfterTheAnswerAreNotUsedForTheNextQuestion(): void {
        // E.g. a double Enter: the second one must not accept the default answer of the next question.
        $prompt = new TestPrompt(['y' . static::ENTER, 'n']);

        self::assertTrue($prompt->confirm('First?'));
        self::assertFalse($prompt->confirm('Second?', true));
    }

    // Decoration.

    public function testDecoratedPrompt(): void {
        $prompt = new TestPrompt([static::ENTER], isDecorated: true);
        $prompt->confirm('Continue?', true);

        self::assertStringContainsString("\033[32m?\033[0m Continue? \033[1;7;36m Yes \033[0m \033[2m No \033[0m", $prompt->getOutput());
        self::assertSame(['? Continue? Yes'], $prompt->getScreen());
    }

    public function testDecoratedHint(): void {
        $prompt = new TestPrompt([static::ENTER], isDecorated: true);
        $prompt->choice('Fruit?', static::FRUITS);

        self::assertStringContainsString("Fruit? \033[2m(↑/↓, Enter)\033[0m", $prompt->getOutput());
    }

    protected function outputBeforeAnswer(TestPrompt $prompt): string {
        $output = $prompt->getOutput();

        // The answer replaces the prompt lines: the last clearing.
        return substr($output, 0, (int)strrpos($output, "\r\033[J"));
    }
}

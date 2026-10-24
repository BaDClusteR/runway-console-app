<?php

declare(strict_types=1);

namespace Runway\Console\Tests\E2E;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Console\Tests\Support\TerminalSession;

/**
 * The app in a real (pseudo) terminal, as the user sees it: the colors, the redrawing, the prompts with the key
 * presses, and the terminal state after the app.
 */
class TerminalTest extends TestCase {
    protected const string ENTER = "\r";

    protected const string ESC = "\033";

    protected const string CTRL_C = "\x03";

    protected const string DOWN = "\033[B";

    protected const string RIGHT = "\033[C";

    protected const string BACKSPACE = "\x7f";

    protected const string HIDE_CURSOR = "\033[?25l";

    protected const string SHOW_CURSOR = "\033[?25h";

    protected function setUp(): void {
        if (!TerminalSession::isAvailable()) {
            self::markTestSkipped('A pseudo-terminal is not available ("script" is required).');
        }
    }

    /**
     * The raw mode is turned off and the cursor is visible again.
     */
    protected static function assertTerminalRestored(TerminalSession $session): void {
        $settings = $session->getSettingsAfterExit();

        foreach (['icanon', 'echo', 'isig'] as $flag) {
            self::assertContains($flag, $settings);
            self::assertNotContains("-{$flag}", $settings);
        }

        $output = $session->getOutput();
        self::assertGreaterThan(
            (int)strrpos($output, static::HIDE_CURSOR),
            (int)strrpos($output, static::SHOW_CURSOR),
            'The cursor is hidden.'
        );
    }

    public function testColors(): void {
        $session = new TerminalSession(['demo:table']);

        self::assertSame(0, $session->finish());
        self::assertStringContainsString("\033[32mok\033[0m", $session->getOutput());
        self::assertSame(
            ['┌────┬────────┐', '│ ID │ Status │', '├────┼────────┤', '│ 1  │ ok     │', '│ 2  │ failed │', '└────┴────────┘'],
            $session->getScreen()
        );
    }

    public function testNoColor(): void {
        $session = new TerminalSession(['demo:table'], env: ['NO_COLOR' => '1']);

        self::assertSame(0, $session->finish());
        self::assertStringNotContainsString("\033[", $session->getOutput());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function terminalWidthProvider(): iterable {
        yield 'wide' => [120];
        yield 'narrow' => [40];
        yield 'very narrow' => [20];
    }

    /**
     * The messages get shorter, and the first one is wider than the terminal: neither the tail of a longer message
     * nor a wrapped line is left on the screen.
     */
    #[DataProvider('terminalWidthProvider')]
    public function testProgressBarLeavesNoLeftovers(int $columns): void {
        $session = new TerminalSession(['demo:progress'], $columns);

        self::assertSame(0, $session->finish());

        $screen = $session->getScreen();

        self::assertCount(1, $screen, implode("\n", $screen));
        self::assertSame(
            mb_strimwidth(' 3/3 [============================] 100% Done', 0, $columns - 1, '…'),
            $screen[0]
        );
    }

    public function testConfirm(): void {
        $session = new TerminalSession(['demo:prompt', 'confirm']);
        $session->waitFor('Continue?')->press(static::RIGHT, static::RIGHT, 'y');

        self::assertSame(0, $session->finish());
        self::assertSame(['? Continue? Yes', 'Answer: true'], $session->getScreen());
        static::assertTerminalRestored($session);
    }

    public function testChoice(): void {
        $session = new TerminalSession(['demo:prompt', 'choice']);
        $session->waitFor('Fruit?')->press(static::DOWN, static::DOWN, static::ENTER);

        self::assertSame(0, $session->finish());
        self::assertSame(['? Fruit? Cherry', "Answer: 'cherry'"], $session->getScreen());
        static::assertTerminalRestored($session);
    }

    public function testAskWithValidation(): void {
        $session = new TerminalSession(['demo:prompt', 'ask']);
        $session
            ->waitFor('Name?')
            ->press(static::ENTER)
            // The validator runs outside of the raw mode, so the keys are typed when the prompt is shown again.
            ->waitFor(static::HIDE_CURSOR, 2)
            ->press('J', 'o', 'x', static::BACKSPACE, 'e', static::ENTER);

        self::assertSame(0, $session->finish());
        self::assertSame(
            ['? Name?', '✖ The name is required.', '? Name? Joe', "Answer: 'Joe'"],
            $session->getScreen()
        );
        static::assertTerminalRestored($session);
    }

    public function testSecretIsNotShown(): void {
        $session = new TerminalSession(['demo:prompt', 'secret']);
        $session->waitFor('Password?')->press('s3cr3t', static::ENTER);

        self::assertSame(0, $session->finish());
        self::assertSame(['? Password? ••••••', "Answer: 's3cr3t'"], $session->getScreen());
        // Neither the prompt nor the terminal echo shows the password: only the command prints it.
        self::assertSame(1, substr_count($session->getOutput(), 's3cr3t'));
        static::assertTerminalRestored($session);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function cancelKeyProvider(): iterable {
        yield 'esc' => [static::ESC];
        // Read as a key in the raw mode: the app is not killed, so the terminal is restored.
        yield 'ctrl+c' => [static::CTRL_C];
    }

    #[DataProvider('cancelKeyProvider')]
    public function testCancel(string $key): void {
        $session = new TerminalSession(['demo:prompt', 'choice']);
        $session->waitFor('Fruit?')->press(static::DOWN, $key);

        self::assertSame(1, $session->finish());
        self::assertSame([' Cancelled.'], $session->getScreen());
        static::assertTerminalRestored($session);
    }

    public function testExitCode(): void {
        $session = new TerminalSession(['fail', '--code=3']);

        self::assertSame(3, $session->finish());
        self::assertSame(['Partial result', ' Finished with errors'], $session->getScreen());
    }
}

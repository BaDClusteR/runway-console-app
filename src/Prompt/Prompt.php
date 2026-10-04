<?php

declare(strict_types=1);

namespace Runway\Console\Prompt;

use Runway\Console\Exception\ConsoleException;
use Runway\Console\Exception\InvalidAnswerException;
use Runway\Console\Exception\PromptCancelledException;
use Runway\Console\Output\Formatter\IOutputFormatter;

/**
 * Reads single key presses in the terminal's raw mode (via stty). Without stty (e.g. on Windows), the answers are read
 * as lines.
 */
class Prompt implements IPrompt {
    protected const string KEY_UP = "\033[A";

    protected const string KEY_DOWN = "\033[B";

    protected const string KEY_RIGHT = "\033[C";

    protected const string KEY_LEFT = "\033[D";

    protected const string KEY_TAB = "\t";

    protected const array ENTER_KEYS = ["\n", "\r"];

    /** Esc, Ctrl+C, Ctrl+D. */
    protected const array CANCEL_KEYS = ["\033", "\x03", "\x04"];

    protected const array BACKSPACE_KEYS = ["\x7f", "\x08"];

    /** Ctrl+U. */
    protected const string CLEAR_KEY = "\x15";

    /** A pasted text comes as one read, so it's bigger than a key sequence. */
    protected const int READ_SIZE = 1024;

    protected const int MAX_VISIBLE_CHOICES = 10;

    protected const int DEFAULT_TERMINAL_WIDTH = 80;

    protected const string TERMINAL = '/dev/tty';

    /** @var resource */
    protected $input;

    /** @var resource */
    protected $output;

    protected bool $isDecorated;

    /** How many lines the prompt takes now: they are replaced on redrawing. */
    protected int $renderedLinesCount = 0;

    public function __construct(
        protected IOutputFormatter $formatter
    ) {
        $this->input = STDIN;
        $this->output = STDERR;
        $this->isDecorated = (getenv('NO_COLOR') === false || getenv('NO_COLOR') === '') && stream_isatty(STDERR);
    }

    public function isInteractive(): bool {
        return stream_isatty($this->input) && stream_isatty($this->output);
    }

    public function confirm(
        string $question,
        bool   $default = false,
        string $confirmLabel = 'Yes',
        string $cancelLabel = 'No'
    ): bool {
        if (!$this->isInteractive()) {
            return $default;
        }

        $isConfirmed = $this->withRawMode(
            fn(): bool => $this->runConfirm($question, $default, $confirmLabel, $cancelLabel),
            fn(): bool => $this->readConfirmLine($question, $default, $confirmLabel, $cancelLabel)
        );

        $this->writeAnswer($question, $isConfirmed ? $confirmLabel : $cancelLabel);

        return $isConfirmed;
    }

    public function choice(string $question, array $choices, int|string|null $default = null): int|string {
        if ($choices === []) {
            throw new ConsoleException('Nothing to choose from.');
        }

        if (!$this->isInteractive()) {
            if ($default === null) {
                throw new ConsoleException("$question: an answer is required, but the terminal is not interactive.");
            }

            return $default;
        }

        $keys = array_keys($choices);
        $defaultIndex = ($default !== null) ? array_search($default, $keys, true) : false;

        $index = $this->withRawMode(
            fn(): int => $this->runChoice($question, $choices, ($defaultIndex === false) ? 0 : $defaultIndex),
            fn(): int => $this->readChoiceLine($question, $choices, ($defaultIndex === false) ? null : $defaultIndex)
        );

        $this->writeAnswer($question, $choices[$keys[$index]]);

        return $keys[$index];
    }

    public function ask(string $question, ?callable $validator = null, ?string $default = null): mixed {
        if (!$this->isInteractive()) {
            if ($default === null) {
                throw new ConsoleException("$question: an answer is required, but the terminal is not interactive.");
            }

            return $validator ? $validator($default) : $default;
        }

        return $this->askUntilValid(
            $question,
            fn(): string => $this->withRawMode(
                fn(): string => $this->runAsk($question, $default),
                fn(): string => $this->readAskLine($question, $default)
            ),
            $this->formatter->escape(...),
            $validator
        );
    }

    public function secret(string $question, ?callable $validator = null): mixed {
        if (!$this->isInteractive()) {
            throw new ConsoleException("$question: an answer is required, but the terminal is not interactive.");
        }

        return $this->askUntilValid(
            $question,
            // Without the raw mode the typed text would be echoed.
            fn(): string => $this->withRawMode(
                fn(): string => $this->runAsk($question, null, true),
                static fn(): never => throw new ConsoleException(
                    "$question: the input cannot be hidden in this terminal (stty is not available)."
                )
            ),
            $this->mask(...),
            $validator
        );
    }

    /**
     * @param callable(): string                     $read          Reads the answer.
     * @param callable(string $answer): string       $formatAnswer  The answer as it is shown after the question.
     * @param (callable(string $answer): mixed)|null $validator
     *
     * @throws PromptCancelledException
     */
    protected function askUntilValid(
        string    $question,
        callable  $read,
        callable  $formatAnswer,
        ?callable $validator
    ): mixed {
        while (true) {
            $answer = $read();

            $this->writeAnswer($question, $formatAnswer($answer));

            if ($validator === null) {
                return $answer;
            }

            // The validator is called outside of the raw mode, so it can ask its own questions.
            try {
                return $validator($answer);
            } catch (InvalidAnswerException $e) {
                $this->writeFormatted('<fg=red>✖ ' . $this->formatter->escape($e->getMessage()) . "</>\n");
            }
        }
    }

    /**
     * @throws PromptCancelledException
     */
    protected function runConfirm(string $question, bool $default, string $confirmLabel, string $cancelLabel): bool {
        $isConfirmSelected = $default;
        $confirmKey = mb_strtolower(mb_substr($confirmLabel, 0, 1));
        $cancelKey = mb_strtolower(mb_substr($cancelLabel, 0, 1));

        while (true) {
            $buttons = $this->formatButton($confirmLabel, $isConfirmSelected) . ' '
                . $this->formatButton($cancelLabel, !$isConfirmSelected);
            $line = $this->formatQuestion($question) . ' ' . $buttons;

            // The buttons must stay visible: if the line does not fit, they go to the next one.
            $this->redraw($this->fits($line) ? [$line] : [$this->formatQuestion($question), "  $buttons"]);

            $key = $this->readKey();

            if (in_array($key, static::ENTER_KEYS, true)) {
                return $isConfirmSelected;
            }

            if (in_array($key, [static::KEY_LEFT, static::KEY_RIGHT, static::KEY_TAB], true)) {
                $isConfirmSelected = !$isConfirmSelected;
            } elseif ($confirmKey !== $cancelKey && mb_strtolower($key) === $confirmKey) {
                return true;
            } elseif ($confirmKey !== $cancelKey && mb_strtolower($key) === $cancelKey) {
                return false;
            }
        }
    }

    /**
     * @return int The index of the chosen item.
     *
     * @throws PromptCancelledException
     */
    protected function runChoice(string $question, array $choices, int $index): int {
        $labels = array_values($choices);
        $count = count($labels);
        $visibleCount = min($count, static::MAX_VISIBLE_CHOICES);
        $offset = 0;

        while (true) {
            // The selected item is always in the visible window.
            $offset = min(max($offset, $index - $visibleCount + 1), $index);

            $lines = [$this->formatQuestion($question) . ' ' . $this->formatHint('↑/↓, Enter')];

            for ($i = $offset; $i < $offset + $visibleCount; $i++) {
                $lines[] = $this->formatChoice($i + 1, $labels[$i], $i === $index);
            }

            if ($count > $visibleCount) {
                $lines[] = '  ' . $this->formatHint(sprintf('%d/%d', $index + 1, $count));
            }

            $this->redraw($lines);
            $key = $this->readKey();

            if (in_array($key, static::ENTER_KEYS, true)) {
                return $index;
            }

            if ($key === static::KEY_UP) {
                $index = ($index - 1 + $count) % $count;
            } elseif ($key === static::KEY_DOWN || $key === static::KEY_TAB) {
                $index = ($index + 1) % $count;
            } elseif (ctype_digit($key) && (int)$key >= 1 && (int)$key <= $count) {
                $index = (int)$key - 1;
            }
        }
    }

    /**
     * A simple line editor: typing, pasting, Backspace, Ctrl+U to clear.
     *
     * @param bool $isMasked Show the typed characters as bullets.
     *
     * @throws PromptCancelledException
     */
    protected function runAsk(string $question, ?string $default, bool $isMasked = false): string {
        $value = '';
        // A multibyte character can come in parts.
        $incompleteBytes = '';

        while (true) {
            $prefix = $this->formatQuestion($question) . ' ';
            $hint = ($value === '' && $default !== null) ? $this->formatHint($this->formatter->escape($default)) . ' ' : '';
            $cursor = $this->isDecorated ? '<options=reverse> </>' : '_';
            // The input must stay visible: if the question takes the line, the input goes to the next one.
            $isSeparateLine = !$this->fits($prefix . $hint . '__________');
            $inputPrefix = $isSeparateLine ? '<fg=cyan>❯</> ' : $prefix;
            $visibleWidth = $this->getTerminalWidth() - 2 - mb_strwidth($this->formatter->format($inputPrefix . $hint, false));
            $shownValue = $this->getTail($isMasked ? $this->mask($value) : $value, $visibleWidth);
            $inputLine = $inputPrefix . $hint . $this->formatter->escape($shownValue) . $cursor;

            $this->redraw($isSeparateLine ? [$this->formatQuestion($question), $inputLine] : [$inputLine]);

            $key = $this->readKey();

            if (in_array($key, static::ENTER_KEYS, true)) {
                return ($value !== '') ? $value : ($default ?? '');
            }

            if (in_array($key, static::BACKSPACE_KEYS, true)) {
                $value = mb_substr($value, 0, -1);
                continue;
            }

            if ($key === static::CLEAR_KEY) {
                $value = '';
                continue;
            }

            // Arrows and other special keys are not supported.
            if (str_starts_with($key, "\033")) {
                continue;
            }

            $text = $incompleteBytes . $key;

            if (!mb_check_encoding($text, 'UTF-8')) {
                // A UTF-8 character is at most 4 bytes: longer invalid input is just dropped.
                $incompleteBytes = (strlen($text) < 4) ? $text : '';
                continue;
            }

            $incompleteBytes = '';
            // A pasted text with a line break: the first line is the answer.
            $lines = preg_split('/\r\n|\r|\n/', $text);
            $value .= preg_replace('/[\x00-\x1F\x7F]/', '', $lines[0]);

            if (count($lines) > 1) {
                return ($value !== '') ? $value : ($default ?? '');
            }
        }
    }

    /**
     * A bullet for every character.
     */
    protected function mask(string $text): string {
        return str_repeat('•', mb_strlen($text));
    }

    /**
     * The end of the text that fits the width, e.g. "…very long answer".
     */
    protected function getTail(string $text, int $width): string {
        if (mb_strwidth($text) <= $width) {
            return $text;
        }

        $tail = '';

        for ($i = mb_strlen($text) - 1; $i >= 0 && mb_strwidth(mb_substr($text, $i, 1) . $tail) < $width; $i--) {
            $tail = mb_substr($text, $i, 1) . $tail;
        }

        return "…$tail";
    }

    /**
     * @throws PromptCancelledException
     */
    protected function readAskLine(string $question, ?string $default): string {
        $hint = ($default !== null) ? ' ' . $this->formatHint($this->formatter->escape($default)) : '';

        $this->writeFormatted($this->formatQuestion($question) . $hint . ' ');
        $answer = rtrim($this->readLine(), "\r\n");

        return ($answer !== '') ? $answer : ($default ?? '');
    }

    /**
     * @template T
     *
     * @param callable(): T $interactive Reads the keys in the raw mode.
     * @param callable(): T $fallback    Reads lines, if the raw mode is not available.
     *
     * @return T
     */
    protected function withRawMode(callable $interactive, callable $fallback): mixed {
        $state = $this->getTerminalState();

        if ($state === null) {
            return $fallback();
        }

        // No line buffering and echo; Ctrl+C is read as a key, so the terminal is always restored.
        $this->stty('-icanon -echo -isig min 1');
        $this->write("\033[?25l");

        try {
            return $interactive();
        } finally {
            $this->write("\033[?25h");
            $this->stty(escapeshellarg($state));
        }
    }

    /**
     * Replaces the previously drawn lines of the prompt.
     *
     * @param string[] $lines
     */
    protected function redraw(array $lines): void {
        $this->clearRendered();
        $this->write(implode("\n", array_map($this->fitLine(...), $lines)));
        $this->renderedLinesCount = count($lines);
    }

    protected function clearRendered(): void {
        if ($this->renderedLinesCount > 1) {
            $this->write(sprintf("\033[%dA", $this->renderedLinesCount - 1));
        }

        if ($this->renderedLinesCount > 0) {
            $this->write("\r\033[J");
        }
    }

    /**
     * The final state of the prompt: the question with the answer.
     */
    protected function writeAnswer(string $question, string $answer): void {
        $this->clearRendered();
        $this->renderedLinesCount = 0;
        $this->writeFormatted($this->formatQuestion($question) . " <fg=cyan>$answer</>\n");
    }

    /**
     * A wrapped line would break the redrawing, so long lines are cut (without the formatting then).
     */
    protected function fitLine(string $line): string {
        return $this->fits($line)
            ? $this->formatter->format($line, $this->isDecorated)
            : mb_strimwidth($this->formatter->format($line, false), 0, $this->getTerminalWidth() - 1, '…');
    }

    protected function fits(string $line): bool {
        // The last column is not used: the cursor there wraps the line in some terminals.
        return mb_strwidth($this->formatter->format($line, false)) <= $this->getTerminalWidth() - 1;
    }

    protected function formatQuestion(string $question): string {
        return "<fg=green>?</> $question";
    }

    protected function formatHint(string $hint): string {
        return $this->isDecorated ? "<options=dim>($hint)</>" : "($hint)";
    }

    protected function formatButton(string $label, bool $isSelected): string {
        if (!$this->isDecorated) {
            return $isSelected ? "[$label]" : " $label ";
        }

        return $isSelected
            ? "<options=bold,reverse;fg=cyan> $label </>"
            : "<options=dim> $label </>";
    }

    protected function formatChoice(int $number, string $label, bool $isSelected): string {
        return $isSelected
            ? "<fg=cyan>❯ $number. $label</>"
            : "  $number. $label";
    }

    /**
     * @throws PromptCancelledException
     */
    protected function readKey(): string {
        $key = fread($this->input, static::READ_SIZE);

        if ($key === false || $key === '' || in_array($key, static::CANCEL_KEYS, true)) {
            $this->clearRendered();

            throw new PromptCancelledException();
        }

        return $key;
    }

    /**
     * @throws PromptCancelledException
     */
    protected function readConfirmLine(string $question, bool $default, string $confirmLabel, string $cancelLabel): bool {
        $hint = $default ? "$confirmLabel/" . mb_strtolower($cancelLabel) : mb_strtolower($confirmLabel) . "/$cancelLabel";

        while (true) {
            $this->writeFormatted($this->formatQuestion($question) . ' ' . $this->formatHint($hint) . ' ');
            $answer = mb_strtolower(trim($this->readLine()));

            if ($answer === '') {
                return $default;
            }

            if (in_array($answer, ['y', 'yes', mb_strtolower($confirmLabel)], true)) {
                return true;
            }

            if (in_array($answer, ['n', 'no', mb_strtolower($cancelLabel)], true)) {
                return false;
            }
        }
    }

    /**
     * @return int The index of the chosen item.
     *
     * @throws PromptCancelledException
     */
    protected function readChoiceLine(string $question, array $choices, ?int $defaultIndex): int {
        $labels = array_values($choices);

        $this->writeFormatted($this->formatQuestion($question) . "\n");

        foreach ($labels as $i => $label) {
            $this->writeFormatted('  ' . ($i + 1) . ". $label\n");
        }

        while (true) {
            $this->writeFormatted('  Number' . (($defaultIndex !== null) ? ' [' . ($defaultIndex + 1) . ']' : '') . ': ');
            $answer = trim($this->readLine());

            if ($answer === '' && $defaultIndex !== null) {
                return $defaultIndex;
            }

            if (ctype_digit($answer) && (int)$answer >= 1 && (int)$answer <= count($labels)) {
                return (int)$answer - 1;
            }
        }
    }

    /**
     * @throws PromptCancelledException On the end of input (Ctrl+D).
     */
    protected function readLine(): string {
        $line = fgets($this->input);

        if ($line === false) {
            $this->write("\n");

            throw new PromptCancelledException();
        }

        return $line;
    }

    /**
     * @return string|null Null if the terminal settings cannot be changed (no stty).
     */
    protected function getTerminalState(): ?string {
        if (!function_exists('shell_exec') || !is_readable(static::TERMINAL)) {
            return null;
        }

        $state = trim((string)$this->stty('-g'));

        return ($state !== '') ? $state : null;
    }

    protected function getTerminalWidth(): int {
        $size = function_exists('shell_exec') ? trim((string)$this->stty('size')) : '';
        $columns = (int)(explode(' ', $size)[1] ?? 0);

        return ($columns > 0) ? $columns : static::DEFAULT_TERMINAL_WIDTH;
    }

    protected function stty(string $arguments): ?string {
        $result = shell_exec('stty ' . $arguments . ' < ' . static::TERMINAL . ' 2>/dev/null');

        return is_string($result) ? $result : null;
    }

    protected function writeFormatted(string $message): void {
        $this->write($this->formatter->format($message, $this->isDecorated));
    }

    protected function write(string $text): void {
        fwrite($this->output, $text);
    }
}

<?php

declare(strict_types=1);

namespace Runway\Console\Prompt;

use Runway\Console\Exception\ConsoleException;
use Runway\Console\Exception\PromptCancelledException;

/**
 * Interactive questions in the terminal. The prompts are written to STDERR, so they don't mix with the command output
 * when it's redirected.
 */
interface IPrompt {
    /**
     * Whether the user can answer: STDIN and STDERR are terminals. Otherwise, the prompts return the default answers.
     */
    public function isInteractive(): bool;

    /**
     * A question with two buttons: switched with the arrow keys or Tab, chosen with Enter or the first letter of the
     * label.
     *
     * @return bool True for the confirm button. The default answer, if not interactive.
     *
     * @throws PromptCancelledException On Ctrl+C or Esc.
     */
    public function confirm(
        string $question,
        bool   $default = false,
        string $confirmLabel = 'Yes',
        string $cancelLabel = 'No'
    ): bool;

    /**
     * A list to choose from: with the arrow keys (or the item number) and Enter. Long lists are scrolled.
     *
     * @param array<int|string, string> $choices Key => label. The labels are formatted (escape the user data).
     * @param int|string|null           $default The key of the initially selected item (the first one by default).
     *
     * @return int|string The key of the chosen item.
     *
     * @throws PromptCancelledException On Ctrl+C or Esc.
     * @throws ConsoleException If not interactive and there is no default.
     */
    public function choice(string $question, array $choices, int|string|null $default = null): int|string;

    /**
     * A text answer: typing, pasting, Backspace, Ctrl+U to clear. Asked again while the validator rejects it (throws
     * InvalidAnswerException).
     *
     * @param (callable(string $answer): mixed)|null $validator Returns the result of ask().
     * @param string|null                            $default   The answer on an empty input.
     *
     * @return mixed The answer, or what the validator returned.
     *
     * @throws PromptCancelledException On Ctrl+C or Esc.
     * @throws ConsoleException If not interactive and there is no default.
     */
    public function ask(string $question, ?callable $validator = null, ?string $default = null): mixed;

    /**
     * Like ask(), but the typed characters are masked (e.g. for a password). There is no default answer: an empty
     * input is an empty string.
     *
     * @param (callable(string $answer): mixed)|null $validator Returns the result of secret().
     *
     * @return mixed The answer, or what the validator returned.
     *
     * @throws PromptCancelledException On Ctrl+C or Esc.
     * @throws ConsoleException If not interactive or the input cannot be hidden (no stty, e.g. on Windows).
     */
    public function secret(string $question, ?callable $validator = null): mixed;
}

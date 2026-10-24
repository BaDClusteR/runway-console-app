<?php

declare(strict_types=1);

namespace Runway\Console\Tests\Support;

use Runway\Console\Output\Formatter\OutputFormatter;
use Runway\Console\Prompt\Prompt;
use RuntimeException;

/**
 * A prompt that reads the given key presses and writes to memory. The terminal settings are not changed: the stty
 * calls are recorded instead.
 */
class TestPrompt extends Prompt {
    /** @var string[] */
    public array $sttyCalls = [];

    /** @var string[][] Every drawn state of the prompt (without the formatting). */
    public array $frames = [];

    /**
     * @param string[] $keys            Key presses (or pasted texts); for the line mode, the typed text.
     * @param bool     $isRawModeAvailable False is like no stty (e.g. on Windows): the answers are read as lines.
     */
    public function __construct(
        array          $keys = [],
        int            $terminalWidth = 80,
        public bool    $isTerminal = true,
        protected bool $isRawModeAvailable = true,
        bool           $isDecorated = false
    ) {
        parent::__construct(new OutputFormatter(), new FixedWidthTerminal($terminalWidth));

        $this->input = KeyStream::open($keys);
        $this->output = fopen('php://memory', 'w+b') ?: throw new RuntimeException('The output cannot be opened.');
        $this->isDecorated = $isDecorated;
    }

    public function isInteractive(): bool {
        return $this->isTerminal;
    }

    /**
     * Writes to the terminal like the command does, e.g. before the prompt.
     */
    public function writeOutput(string $text): void {
        $this->write($text);
    }

    /**
     * Everything written to the terminal.
     */
    public function getOutput(): string {
        rewind($this->output);

        return (string)stream_get_contents($this->output);
    }

    /**
     * @return string[] What the user sees now.
     */
    public function getScreen(): array {
        return VirtualScreen::render($this->getOutput());
    }

    /**
     * @return string[]
     */
    public function getLastFrame(): array {
        return end($this->frames) ?: [];
    }

    protected function redraw(array $lines): void {
        $this->frames[] = array_map(fn(string $line): string => $this->formatter->format($line, false), $lines);

        parent::redraw($lines);
    }

    protected function getTerminalState(): ?string {
        return $this->isRawModeAvailable ? 'saved-state' : null;
    }

    protected function stty(string $arguments): ?string {
        $this->sttyCalls[] = $arguments;

        return '';
    }
}

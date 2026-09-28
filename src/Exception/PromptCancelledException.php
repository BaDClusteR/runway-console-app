<?php

declare(strict_types=1);

namespace Runway\Console\Exception;

/**
 * The user cancelled a prompt (Ctrl+C or Esc): the command is stopped, as nothing was chosen.
 */
class PromptCancelledException extends ConsoleException {
    public function __construct(string $message = 'Cancelled.') {
        parent::__construct($message);
    }
}

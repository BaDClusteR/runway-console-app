<?php

declare(strict_types=1);

namespace Runway\Console\Exception;

/**
 * Thrown by a prompt answer validator: the message is shown, and the question is asked again.
 */
class InvalidAnswerException extends ConsoleException {
}

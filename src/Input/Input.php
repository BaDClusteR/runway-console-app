<?php

declare(strict_types=1);

namespace Runway\Console\Input;

class Input implements IInput {
    /**
     * @param array<string, string|null> $arguments
     * @param array<string, string|null> $options
     */
    public function __construct(
        private readonly array $arguments = [],
        private readonly array $options = []
    ) {}

    public function getArgument(string $name): ?string {
        return $this->arguments[$name] ?? null;
    }

    public function hasArgument(string $name): bool {
        return array_key_exists($name, $this->arguments);
    }

    public function getOption(string $name): ?string {
        return $this->options[$name] ?? null;
    }

    public function hasOption(string $name): bool {
        return array_key_exists($name, $this->options);
    }

    public function getArguments(): array {
        return $this->arguments;
    }

    public function getOptions(): array {
        return $this->options;
    }
}

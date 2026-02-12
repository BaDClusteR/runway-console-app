<?php

declare(strict_types=1);

namespace Runway\Console\Input;

interface IInput {
    public function getArgument(string $name): ?string;

    public function hasArgument(string $name): bool;

    public function getOption(string $name): ?string;

    public function hasOption(string $name): bool;

    /**
     * @return array<string, string|null>
     */
    public function getArguments(): array;

    /**
     * @return array<string, string|null>
     */
    public function getOptions(): array;
}

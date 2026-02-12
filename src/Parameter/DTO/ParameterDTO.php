<?php

declare(strict_types=1);

namespace Runway\Console\Parameter\DTO;

use Runway\Console\Parameter\Enum\ParameterModeEnum;
use Runway\Console\Parameter\Enum\ParameterTypeEnum;

readonly class ParameterDTO {
    public function __construct(
        private string            $name,
        private ParameterTypeEnum $type,
        private ParameterModeEnum $mode,
        private string            $description = '',
        private ?string           $default = null,
        private ?string           $shortcut = null
    ) {}

    public function getName(): string {
        return $this->name;
    }

    public function getType(): ParameterTypeEnum {
        return $this->type;
    }

    public function getMode(): ParameterModeEnum {
        return $this->mode;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function getDefault(): ?string {
        return $this->default;
    }

    public function getShortcut(): ?string {
        return $this->shortcut;
    }
}

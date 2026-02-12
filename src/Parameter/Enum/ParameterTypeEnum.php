<?php

declare(strict_types=1);

namespace Runway\Console\Parameter\Enum;

enum ParameterTypeEnum: string {
    case ARGUMENT = 'argument';
    case OPTION = 'option';
}

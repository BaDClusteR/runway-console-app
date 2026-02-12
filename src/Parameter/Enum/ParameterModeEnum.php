<?php

declare(strict_types=1);

namespace Runway\Console\Parameter\Enum;

enum ParameterModeEnum: string {
    case REQUIRED = 'required';
    case OPTIONAL = 'optional';

    case VALUE_REQUIRED = 'value_required';
    case VALUE_OPTIONAL = 'value_optional';
    case VALUE_NONE = 'value_none';
}

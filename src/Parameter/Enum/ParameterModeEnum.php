<?php

declare(strict_types=1);

namespace Runway\Console\Parameter\Enum;

enum ParameterModeEnum: string {
    case REQUIRED = 'required';
    case OPTIONAL = 'optional';

    case VALUE_REQUIRED = 'value_required';
    /** Without a value the option is an empty string (and the default, if it's not given at all). */
    case VALUE_OPTIONAL = 'value_optional';
    /** A flag: "1" if given. */
    case VALUE_NONE = 'value_none';
}

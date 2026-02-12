<?php

declare(strict_types=1);

namespace Runway\Console\Input\Parser;

use Runway\Console\Input\IInput;
use Runway\Console\Parameter\DTO\ParameterDTO;

interface IInputParser {
    /**
     * @param string[]       $tokens
     * @param ParameterDTO[] $parameterDTOs
     */
    public function parse(array $tokens, array $parameterDTOs): IInput;
}

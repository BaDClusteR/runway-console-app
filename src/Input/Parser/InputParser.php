<?php

declare(strict_types=1);

namespace Runway\Console\Input\Parser;

use Runway\Console\Exception\InvalidParameterException;
use Runway\Console\Input\IInput;
use Runway\Console\Input\Input;
use Runway\Console\Parameter\DTO\ParameterDTO;
use Runway\Console\Parameter\Enum\ParameterModeEnum;
use Runway\Console\Parameter\Enum\ParameterTypeEnum;

class InputParser implements IInputParser {
    public function parse(array $tokens, array $parameterDTOs): IInput {
        $optionDefs = $this->filterByType($parameterDTOs, ParameterTypeEnum::OPTION);
        $argumentDefs = $this->filterByType($parameterDTOs, ParameterTypeEnum::ARGUMENT);

        $parsedOptions = [];
        $positionalTokens = [];

        $i = 0;
        $tokenCount = count($tokens);

        while ($i < $tokenCount) {
            $token = $tokens[$i];

            if ($token === '--') {
                $i++;
                while ($i < $tokenCount) {
                    $positionalTokens[] = $tokens[$i];
                    $i++;
                }
                break;
            }

            if (str_starts_with($token, '--')) {
                $this->parseLongOption($token, $tokens, $i, $optionDefs, $parsedOptions);
            } elseif (str_starts_with($token, '-') && $token !== '-') {
                $this->parseShortOption($token, $tokens, $i, $optionDefs, $parsedOptions);
            } else {
                $positionalTokens[] = $token;
            }

            $i++;
        }

        $parsedArguments = $this->mapArguments($positionalTokens, $argumentDefs);

        foreach ($optionDefs as $def) {
            $name = $def->getName();
            if (!array_key_exists($name, $parsedOptions)) {
                $parsedOptions[$name] = $def->getDefault();
            }
        }

        return new Input($parsedArguments, $parsedOptions);
    }

    /**
     * @param ParameterDTO[] $defs
     * @return ParameterDTO[]
     */
    protected function filterByType(array $defs, ParameterTypeEnum $type): array {
        return array_values(
            array_filter(
                $defs,
                static fn(ParameterDTO $d): bool => $d->getType() === $type
            )
        );
    }

    protected function parseLongOption(
        string $token,
        array  $tokens,
        int    &$i,
        array  $optionDefs,
        array  &$parsedOptions
    ): void {
        $nameValue = substr($token, 2);

        if (str_contains($nameValue, '=')) {
            [$name, $value] = explode('=', $nameValue, 2);
            $parsedOptions[$name] = $value;
            return;
        }

        $name = $nameValue;
        $def = $this->findOptionByName($name, $optionDefs);

        if ($def && $def->getMode() === ParameterModeEnum::VALUE_NONE) {
            $parsedOptions[$name] = '1';
            return;
        }

        if (isset($tokens[$i + 1]) && !str_starts_with($tokens[$i + 1], '-')) {
            $i++;
            $parsedOptions[$name] = $tokens[$i];
        } else {
            $parsedOptions[$name] = '1';
        }
    }

    protected function parseShortOption(
        string $token,
        array  $tokens,
        int    &$i,
        array  $optionDefs,
        array  &$parsedOptions
    ): void {
        $shortcut = substr($token, 1);
        $def = $this->findOptionByShortcut($shortcut, $optionDefs);

        if ($def) {
            $name = $def->getName();

            if ($def->getMode() === ParameterModeEnum::VALUE_NONE) {
                $parsedOptions[$name] = '1';
                return;
            }

            if (isset($tokens[$i + 1]) && !str_starts_with($tokens[$i + 1], '-')) {
                $i++;
                $parsedOptions[$name] = $tokens[$i];
            } else {
                $parsedOptions[$name] = '1';
            }

            return;
        }

        foreach (str_split($shortcut) as $char) {
            $charDef = $this->findOptionByShortcut($char, $optionDefs);

            if ($charDef) {
                $parsedOptions[$charDef->getName()] = '1';
            }
        }
    }

    /**
     * @param ParameterDTO[] $defs
     */
    protected function findOptionByName(string $name, array $defs): ?ParameterDTO {
        foreach ($defs as $def) {
            if ($def->getName() === $name) {
                return $def;
            }
        }

        return null;
    }

    /**
     * @param ParameterDTO[] $defs
     */
    protected function findOptionByShortcut(string $shortcut, array $defs): ?ParameterDTO {
        foreach ($defs as $def) {
            if ($def->getShortcut() === $shortcut) {
                return $def;
            }
        }

        return null;
    }

    /**
     * @param string[]       $positionalTokens
     * @param ParameterDTO[] $argumentDefs
     * @return array<string, string|null>
     */
    protected function mapArguments(array $positionalTokens, array $argumentDefs): array {
        $result = [];

        foreach ($argumentDefs as $index => $def) {
            $name = $def->getName();

            if (isset($positionalTokens[$index])) {
                $result[$name] = $positionalTokens[$index];
            } elseif ($def->getMode() === ParameterModeEnum::REQUIRED) {
                throw new InvalidParameterException(
                    "Missing required argument: {$name}"
                );
            } else {
                $result[$name] = $def->getDefault();
            }
        }

        return $result;
    }
}

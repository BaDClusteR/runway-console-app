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
            $def = $this->getOptionByName($name, $optionDefs);

            if ($def->getMode() === ParameterModeEnum::VALUE_NONE) {
                throw new InvalidParameterException("Option --{$name} does not accept a value.");
            }

            $parsedOptions[$name] = $value;
            return;
        }

        $def = $this->getOptionByName($nameValue, $optionDefs);
        $parsedOptions[$nameValue] = $this->readOptionValue($def, "--{$nameValue}", $tokens, $i);
    }

    /**
     * @param ParameterDTO[] $optionDefs
     */
    protected function getOptionByName(string $name, array $optionDefs): ParameterDTO {
        return $this->findOptionByName($name, $optionDefs)
            ?? throw new InvalidParameterException("Unknown option: --{$name}");
    }

    /**
     * Reads the option value from the next token, if the option accepts a value.
     */
    protected function readOptionValue(ParameterDTO $def, string $displayName, array $tokens, int &$i): string {
        if ($def->getMode() === ParameterModeEnum::VALUE_NONE) {
            return '1';
        }

        $nextToken = $tokens[$i + 1] ?? null;

        // Negative numbers are values, not options.
        if ($nextToken !== null && (!str_starts_with($nextToken, '-') || is_numeric($nextToken))) {
            $i++;

            return $nextToken;
        }

        if ($def->getMode() === ParameterModeEnum::VALUE_REQUIRED) {
            throw new InvalidParameterException("Option {$displayName} requires a value.");
        }

        // An empty string, not "1": the option given without a value must differ from the option with value "1".
        return '';
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
            $parsedOptions[$def->getName()] = $this->readOptionValue($def, "-{$shortcut}", $tokens, $i);

            return;
        }

        // A cluster of flags, e.g. "-abc".
        foreach (str_split($shortcut) as $char) {
            $charDef = $this->findOptionByShortcut($char, $optionDefs)
                ?? throw new InvalidParameterException("Unknown option: -{$char}");

            if ($charDef->getMode() === ParameterModeEnum::VALUE_REQUIRED) {
                throw new InvalidParameterException("Option -{$char} requires a value and cannot be combined.");
            }

            $parsedOptions[$charDef->getName()] = ($charDef->getMode() === ParameterModeEnum::VALUE_NONE) ? '1' : '';
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
        // E.g. a value with spaces that was not quoted.
        if (count($positionalTokens) > count($argumentDefs)) {
            throw new InvalidParameterException(
                "Too many arguments: expected at most " . count($argumentDefs) . ", got " . count($positionalTokens)
                . ". Values with spaces should be quoted."
            );
        }

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

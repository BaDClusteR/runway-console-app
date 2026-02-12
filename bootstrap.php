<?php

declare(strict_types=1);

use Runway\Service\Provider\PathsProvider;

const CONSOLE_ROOT = __DIR__;
const CONSOLE_CONFIG_ROOT = CONSOLE_ROOT . "/config";

$pathsProvider = PathsProvider::getInstance();
$pathsProvider->addConfigDirectory(CONSOLE_CONFIG_ROOT);

<?php

/**
 * A console app like in a real project: the real DI container with the library config and the fixture commands.
 *
 * Environment variables:
 *   FIXTURE_ENV_FILE  An env file to load (e.g. debug.env for the debug mode).
 */

declare(strict_types=1);

use Runway\Console\IApplication;
use Runway\Service\Provider\PathsProvider;
use Runway\Singleton\Container;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

/**
 * The library config as it is shipped: without the local overrides (config/dev.yaml is not a part of the library,
 * see .gitignore), so the tests do not depend on the developer's setup.
 */
function getLibraryConfigDirectory(): string {
    $directory = sys_get_temp_dir() . '/runway-console-tests-config-' . getmypid();

    if (!is_dir($directory)) {
        mkdir($directory, 0700, true);
    }

    foreach (glob(CONSOLE_CONFIG_ROOT . '/*.yaml') as $file) {
        if (basename($file) !== 'dev.yaml') {
            copy($file, $directory . '/' . basename($file));
        }
    }

    register_shutdown_function(static function () use ($directory): void {
        array_map(unlink(...), glob($directory . '/*.yaml'));
        rmdir($directory);
    });

    return $directory;
}

$configDirectories = new ReflectionProperty(PathsProvider::class, 'configDirectories');
$configDirectories->setValue(
    null,
    array_map(
        static fn(string $directory): string => ($directory === CONSOLE_CONFIG_ROOT) ? getLibraryConfigDirectory() : $directory,
        $configDirectories->getValue()
    )
);

$pathsProvider = PathsProvider::getInstance();
$pathsProvider->addConfigDirectory(__DIR__ . '/config');

$envFile = getenv('FIXTURE_ENV_FILE');

if ($envFile !== false && $envFile !== '') {
    $pathsProvider->addEnvFilePath($envFile);
}

exit(Container::getInstance()->getService(IApplication::class)->run());

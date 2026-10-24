<?php

/**
 * A console app like in a real project: the real DI container with the library config and the fixture commands.
 *
 * Environment variables:
 *   FIXTURE_ENV_FILE        An env file to load (e.g. debug.env for the debug mode).
 *   FIXTURE_INVALID_CONFIG  "1" adds a service with the command tag that is not a command (see config-invalid).
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
    // A new directory with an unpredictable name: an existing one could contain someone else's config.
    $directory = sys_get_temp_dir() . '/runway-console-tests-config-' . bin2hex(random_bytes(8));

    if (!mkdir($directory, 0700)) {
        throw new RuntimeException("Cannot create {$directory}.");
    }

    foreach (glob(CONSOLE_CONFIG_ROOT . '/*.yaml') ?: [] as $file) {
        if (basename($file) !== 'dev.yaml') {
            copy($file, $directory . '/' . basename($file));
        }
    }

    register_shutdown_function(static function () use ($directory): void {
        array_map(unlink(...), glob($directory . '/*.yaml') ?: []);
        rmdir($directory);
    });

    return $directory;
}

/** @var string[] $directories */
$directories = (new ReflectionProperty(PathsProvider::class, 'configDirectories'))->getValue();

(new ReflectionProperty(PathsProvider::class, 'configDirectories'))->setValue(
    null,
    array_map(
        static fn(string $directory): string => ($directory === CONSOLE_CONFIG_ROOT) ? getLibraryConfigDirectory() : $directory,
        $directories
    )
);

$pathsProvider = PathsProvider::getInstance();
$pathsProvider->addConfigDirectory(__DIR__ . '/config');

if (getenv('FIXTURE_INVALID_CONFIG') === '1') {
    $pathsProvider->addConfigDirectory(__DIR__ . '/config-invalid');
}

$envFile = getenv('FIXTURE_ENV_FILE');

if ($envFile !== false && $envFile !== '') {
    $pathsProvider->addEnvFilePath($envFile);
}

$application = Container::getInstance()->getService(IApplication::class);

if (!$application instanceof IApplication) {
    throw new RuntimeException('The application service is not an application.');
}

exit($application->run());

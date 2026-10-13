<?php

declare(strict_types=1);

/*
 * Runs the dev cache against a temporary project in a fresh PHP process, because
 * classes changed by a test cannot be reloaded in the PHPUnit process.
 *
 * Usage: php dev-cache-probe.php <check|load|load-styles|load-sources|load-without-project-base> <cache dir> <project dir>
 * Prints "fresh"/"stale" (check), whether the loaded manifest has styles (load-styles), the directories the
 * manifest was built from (load-sources) or the hook names of the loaded manifest (load).
 */

use Saij\WpAppFramework\Manifest\DevCache\DevCache;
use Saij\WpAppFramework\Manifest\ManifestBuilder;

require __DIR__ . '/../../vendor/autoload.php';

/** @var list<string> $argv Defined by PHP for scripts run from the command line. */
[, $mode, $cacheDirectory, $themeDirectory] = $argv;

spl_autoload_register(static function (string $class) use ($themeDirectory): void {
    // DevCacheOutside\ lives next to the project, outside every base directory (like a dependency loaded from
    // another consumer's vendor directory in development mode).
    foreach (['DevCacheProbe\\' => $themeDirectory . '/src/', 'DevCacheOutside\\' => dirname($themeDirectory) . '/outside/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    }
});

$bases = ['project' => $themeDirectory, 'framework' => (string) realpath(__DIR__ . '/../..')];
if ($mode === 'load-without-project-base') {
    unset($bases['project']);
}

$cache = new DevCache($cacheDirectory, $bases, ManifestBuilder::forProject($themeDirectory));

try {
    if ($mode === 'check') {
        echo $cache->isFresh() ? 'fresh' : 'stale';
    } elseif ($mode === 'load-sources') {
        $cache->load();
        echo implode(',', $cache->getSourceDirectories());
    } elseif ($mode === 'load-styles') {
        echo $cache->load()['styles'] ? 'styles' : 'no styles';
    } else {
        echo implode(',', array_column($cache->load()['hooks'], 'name'));
    }
} catch (Throwable $exception) {
    echo $exception->getMessage();
    exit(1);
}

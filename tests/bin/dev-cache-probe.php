<?php

declare(strict_types=1);

/*
 * Runs the dev cache against a temporary theme in a fresh PHP process, because
 * classes changed by a test cannot be reloaded in the PHPUnit process.
 *
 * Usage: php dev-cache-probe.php <check|load|load-without-theme-base> <cache dir> <theme dir>
 * Prints "fresh"/"stale" (check) or the hook names of the loaded manifest (load).
 */

use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Manifest\DevCache\DevCache;

require __DIR__ . '/../../vendor/autoload.php';

[, $mode, $cacheDirectory, $themeDirectory] = $argv;

spl_autoload_register(static function (string $class) use ($themeDirectory): void {
    $prefix = 'DevCacheProbe\\';
    if (str_starts_with($class, $prefix)) {
        $file = $themeDirectory . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

$bases = ['theme' => $themeDirectory, 'framework' => (string) realpath(__DIR__ . '/../..')];
if ($mode === 'load-without-theme-base') {
    unset($bases['theme']);
}

$cache = new DevCache($cacheDirectory, $bases, ManifestBuilder::forTheme($themeDirectory));

try {
    if ($mode === 'check') {
        echo $cache->isFresh() ? 'fresh' : 'stale';
    } else {
        echo implode(',', array_column($cache->load()['hooks'], 'name'));
    }
} catch (Throwable $exception) {
    echo $exception->getMessage();
    exit(1);
}

<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Filesystem;

use RuntimeException;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Writes files atomically: into a temporary file in the same directory, then
 * renamed over the target, so readers never see a partial file. PHP files are
 * invalidated in OPcache.
 *
 * @internal
 */
#[NoService]
final class AtomicFile
{
    /**
     * @param bool $createDirectory Create the directory of the file if it is missing.
     */
    public static function write(string $file, string $content, bool $createDirectory = false): void
    {
        $directory = dirname($file);
        if ($createDirectory && !is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create directory %s.', $directory));
        }

        $temporary = tempnam($directory, basename($file));
        if ($temporary === false || file_put_contents($temporary, $content) === false) {
            throw new RuntimeException(sprintf('Cannot write a temporary file for %s.', $file));
        }

        chmod($temporary, 0644);

        if (!rename($temporary, $file)) {
            unlink($temporary);
            throw new RuntimeException(sprintf('Cannot move the temporary file to %s.', $file));
        }

        if (str_ends_with($file, '.php') && function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }
}

<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Filesystem;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use SplFileInfo;

/**
 * Removes files and directory trees.
 *
 * @internal
 */
#[NoService]
final class FileTree
{
    /**
     * Removes a file, a symbolic link (not its target) or a directory tree; a missing path is nothing to remove.
     *
     * @throws RuntimeException If an entry cannot be removed.
     */
    public static function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            self::check(@unlink($path), $path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }

        // Without FOLLOW_SYMLINKS: a link to a directory is removed as a link, its target stays untouched.
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            self::check($entry->isDir() && !$entry->isLink() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname()), $entry->getPathname());
        }
        self::check(@rmdir($path), $path);
    }

    /**
     * The warning of a failed unlink() or rmdir() becomes part of the exception instead of a second report.
     *
     * @throws RuntimeException
     */
    private static function check(bool $removed, string $path): void
    {
        if (!$removed) {
            $error = error_get_last();
            throw new RuntimeException(sprintf('Cannot remove %s%s.', $path, $error === null ? '' : ': ' . preg_replace('/^\w+\([^)]*\): /', '', $error['message'])));
        }
    }
}

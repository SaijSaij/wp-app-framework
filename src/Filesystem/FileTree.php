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
            if (!unlink($path)) {
                throw new RuntimeException(sprintf('Cannot remove %s.', $path));
            }

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
            $removed = $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            if (!$removed) {
                throw new RuntimeException(sprintf('Cannot remove %s.', $entry->getPathname()));
            }
        }
        if (!rmdir($path)) {
            throw new RuntimeException(sprintf('Cannot remove %s.', $path));
        }
    }
}

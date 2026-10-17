<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class Files
{
    /**
     * @return list<SplFileInfo> All files and directories below the directory, children first.
     */
    public static function below(string $directory): array
    {
        $entries = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * Deletes the directory with its contents; symbolic links are removed, never followed.
     */
    public static function remove(string $directory): void
    {
        foreach (self::below($directory) as $entry) {
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($directory);
    }
}

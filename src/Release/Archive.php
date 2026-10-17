<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Release;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use ZipArchive;

/**
 * Writes the release ZIP: the build directory below one root folder (the slug),
 * as WordPress expects when it installs a theme or plugin from a ZIP.
 */
#[NoService]
final class Archive
{
    /**
     * @return int Number of files in the archive.
     */
    public static function create(string $directory, string $root, string $file): int
    {
        if (!class_exists(ZipArchive::class)) {
            throw new ReleaseException('The PHP extension zip is required to write the release archive.');
        }

        $entries = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->isLink()) {
                throw new ReleaseException(sprintf('%s is a symbolic link; a release cannot contain symbolic links.', $entry->getPathname()));
            }
            $entries[substr($entry->getPathname(), strlen($directory) + 1)] = $entry->isDir();
        }
        ksort($entries, SORT_STRING);

        $parent = dirname($file);
        if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
            throw new ReleaseException(sprintf('Cannot create directory %s.', $parent));
        }
        $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        $zip = new ZipArchive();
        if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new ReleaseException(sprintf('Cannot create %s.', $temporary));
        }
        $zip->addEmptyDir($root);
        $files = 0;
        foreach ($entries as $path => $isDirectory) {
            $name = $root . '/' . $path;
            $added = $isDirectory ? $zip->addEmptyDir($name) : $zip->addFile($directory . '/' . $path, $name);
            if (!$added) {
                throw new ReleaseException(sprintf('Cannot add %s to %s.', $name, $file));
            }
            $files += $isDirectory ? 0 : 1;
        }
        if (!$zip->close()) {
            throw new ReleaseException(sprintf('Cannot write %s.', $temporary));
        }
        if (!rename($temporary, $file)) {
            throw new ReleaseException(sprintf('Cannot move %s to %s.', $temporary, $file));
        }

        return $files;
    }
}

<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Twig;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Saij\WpAppFramework\DependencyInjection\NoService;
use SplFileInfo;

/**
 * Finds templates of one level that override templates of a lower level, so
 * the build can report them: an override is allowed but must not go unnoticed.
 */
#[NoService]
final class TemplateOverrides
{
    /**
     * @param string $upper Template directory of the higher level, e.g. the theme's `src/`.
     * @param string $lower Template directory of the lower level, e.g. the framework's `src/`.
     * @return list<string> Template names (paths relative to the directories) present in both, sorted.
     */
    public static function find(string $upper, string $lower): array
    {
        return array_values(array_intersect(self::templates($upper), self::templates($lower)));
    }

    /**
     * @return list<string> Sorted template names; empty if the directory does not exist.
     */
    private static function templates(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $names = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'twig') {
                $names[] = substr($file->getPathname(), strlen($directory) + 1);
            }
        }
        sort($names);

        return $names;
    }
}

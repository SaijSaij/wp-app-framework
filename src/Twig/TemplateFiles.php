<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Twig;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Lists the templates of one level (its `src/` directory).
 */
#[NoService]
final class TemplateFiles
{
    /**
     * @param string $directory An existing template directory.
     * @return list<string> Template names (paths relative to the directory, `.twig` files only), sorted.
     */
    public static function names(string $directory): array
    {
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

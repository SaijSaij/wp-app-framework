<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Manifest\Discovery;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Manifest\BuildException;

/**
 * Lists the PHP files of a PSR-4 directory and derives their class names.
 */
#[NoService]
final class ClassDiscovery
{
    private const string IDENTIFIER = '[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*';

    /**
     * Relative paths (with forward slashes) of all PHP files below the directory, sorted.
     *
     * @return list<string>
     */
    public static function files(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Class names by absolute file path, following PSR-4.
     *
     * @return array<string, string>
     * @throws BuildException If a file name is not a valid PSR-4 class name.
     */
    public static function classes(Source $source): array
    {
        $classes = [];
        $errors = [];

        foreach (self::files($source->getDirectory()) as $relative) {
            $segments = explode('/', substr($relative, 0, -4));

            foreach ($segments as $segment) {
                if (preg_match('/^' . self::IDENTIFIER . '$/', $segment) !== 1) {
                    $errors[] = sprintf(
                        '%s/%s is below the PSR-4 directory of %s but is not a valid class file name.',
                        $source->getDirectory(),
                        $relative,
                        $source->getPrefix(),
                    );
                    continue 2;
                }
            }

            $classes[$source->getDirectory() . '/' . $relative] = $source->getPrefix() . implode('\\', $segments);
        }

        if ($errors !== []) {
            throw new BuildException($errors);
        }

        return $classes;
    }
}

<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache;

use Closure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use SplFileInfo;

/**
 * The version of the code that produced a cache entry, part of every key:
 * new code never reads entries of old code.
 *
 * In release mode it combines the code version and the template version of the
 * release manifest with the key of the templates that the active theme and
 * child theme override. In development mode it is a hash of the paths, sizes
 * and modification times of all files in the source and override directories,
 * computed once per request on first use.
 *
 * @internal
 */
#[NoService]
final class CodeGeneration
{
    private ?string $value = null;

    /**
     * @param Closure(): string $compute
     */
    public function __construct(private readonly Closure $compute) {}

    public function get(): string
    {
        return $this->value ??= ($this->compute)();
    }

    /**
     * Hash of the files below the directories (path, size, modification time); missing directories count as empty.
     *
     * @param list<string> $directories
     */
    public static function ofFiles(array $directories): string
    {
        $context = hash_init('xxh128');
        foreach ($directories as $directory) {
            hash_update($context, $directory . "\0");
            if (!is_dir($directory)) {
                continue;
            }
            $entries = [];
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $entries[] = $file->getPathname() . "\0" . $file->getSize() . "\0" . $file->getMTime();
                }
            }
            sort($entries, SORT_STRING);
            hash_update($context, implode("\n", $entries) . "\n");
        }

        return hash_final($context);
    }

    /**
     * Version of the PHP code of a release, written into its manifest: a hash of the contents of the PHP files below
     * the directories (the PSR-4 sources of the consumer and the framework).
     *
     * @param list<string> $directories
     * @return non-empty-string 16 hexadecimal characters.
     */
    public static function codeVersion(array $directories): string
    {
        $context = hash_init('sha256');
        foreach ($directories as $index => $directory) {
            if (!is_dir($directory)) {
                throw new RuntimeException(sprintf('Source directory %s does not exist.', $directory));
            }
            $names = [];
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $names[] = substr($file->getPathname(), strlen($directory) + 1);
                }
            }
            sort($names, SORT_STRING);
            foreach ($names as $name) {
                $contents = file_get_contents($directory . '/' . $name);
                if ($contents === false) {
                    throw new RuntimeException(sprintf('Cannot read %s/%s.', $directory, $name));
                }
                hash_update($context, $index . "\0" . $name . "\0" . strlen($contents) . "\0" . $contents);
            }
        }

        return substr(hash_final($context), 0, 16);
    }
}

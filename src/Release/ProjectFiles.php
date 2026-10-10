<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Release;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * The files of a git working tree that make up a release: everything git does
 * not ignore, including uncommitted changes. Files marked `export-ignore` in
 * `.gitattributes` are needed by the build but are not shipped.
 */
#[NoService]
final class ProjectFiles
{
    /**
     * @param list<string> $files         Paths relative to the directory, sorted.
     * @param list<string> $exportIgnored The subset marked `export-ignore`.
     */
    private function __construct(
        private readonly string $directory,
        private readonly array $files,
        private readonly array $exportIgnored,
    ) {}

    public static function of(Process $process, string $directory): self
    {
        if (!is_dir($directory)) {
            throw new ReleaseException(sprintf('Directory %s does not exist.', $directory));
        }
        if (trim($process->capture(['git', 'rev-parse', '--is-inside-work-tree'], $directory)) !== 'true') {
            throw new ReleaseException(sprintf('%s is not inside a git working tree; the release build copies the files git tracks.', $directory));
        }

        $listed = $process->capture(['git', 'ls-files', '-z', '--cached', '--others', '--exclude-standard'], $directory);
        $files = [];
        foreach (explode("\0", $listed) as $file) {
            // Tracked files deleted in the working tree are not part of the working state.
            if ($file !== '' && (is_file($directory . '/' . $file) || is_link($directory . '/' . $file))) {
                $files[$file] = true;
            }
        }
        $files = array_keys($files);
        sort($files);

        $exportIgnored = [];
        if ($files !== []) {
            // Output of `check-attr -z`: path NUL attribute NUL value NUL, for every path.
            $attributes = explode("\0", $process->capture(['git', 'check-attr', '-z', '--stdin', 'export-ignore'], $directory, implode("\0", $files) . "\0"));
            for ($index = 0; $index + 2 < count($attributes); $index += 3) {
                if ($attributes[$index + 2] === 'set') {
                    $exportIgnored[] = $attributes[$index];
                }
            }
        }

        return new self($directory, $files, $exportIgnored);
    }

    /**
     * @return list<string> Paths relative to the directory, sorted.
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @return list<string> Paths marked `export-ignore`, sorted.
     */
    public function getExportIgnored(): array
    {
        return $this->exportIgnored;
    }

    /**
     * Copies the files into a new or empty directory.
     *
     * @param bool $withExportIgnored false copies only what a distribution archive of the project would contain.
     */
    public function copyTo(string $target, bool $withExportIgnored): void
    {
        $skip = $withExportIgnored ? [] : array_flip($this->exportIgnored);
        foreach ($this->files as $file) {
            if (isset($skip[$file])) {
                continue;
            }
            $source = $this->directory . '/' . $file;
            if (is_link($source)) {
                throw new ReleaseException(sprintf('%s is a symbolic link; a release cannot contain symbolic links.', $source));
            }

            $destination = $target . '/' . $file;
            $parent = dirname($destination);
            if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
                throw new ReleaseException(sprintf('Cannot create directory %s.', $parent));
            }
            if (!copy($source, $destination)) {
                throw new ReleaseException(sprintf('Cannot copy %s to %s.', $source, $destination));
            }
            chmod($destination, fileperms($source) & 0777);
        }
    }
}

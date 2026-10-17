<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Block\Markup;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * The files with block markup of a theme or child theme: block templates, template parts and patterns.
 */
#[NoService]
final readonly class MarkupFiles
{
    /**
     * WordPress reads block templates and template parts from these directories instead of `templates/` and
     * `parts/` as soon as one of them exists (`WP_Theme::get_block_template_folders()`).
     */
    public const array DEPRECATED_DIRECTORIES = ['block-templates', 'block-template-parts'];

    /**
     * @param array<string, MarkupKind> $files                 Kind by path relative to the project, sorted.
     * @param list<string>              $deprecatedDirectories Existing deprecated directories, relative to the project.
     */
    private function __construct(
        private string $projectDirectory,
        private array $files,
        private array $deprecatedDirectories,
    ) {}

    public static function scan(string $projectDirectory): self
    {
        $files = [];
        foreach (MarkupKind::cases() as $kind) {
            $directory = $projectDirectory . '/' . $kind->getDirectory();
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && preg_match($kind->getFilePattern(), $file->getFilename()) === 1) {
                    $files[str_replace('\\', '/', substr($file->getPathname(), strlen($projectDirectory) + 1))] = $kind;
                }
            }
        }
        ksort($files, SORT_STRING);

        $deprecated = array_values(array_filter(
            self::DEPRECATED_DIRECTORIES,
            static fn(string $directory): bool => file_exists($projectDirectory . '/' . $directory),
        ));

        return new self($projectDirectory, $files, $deprecated);
    }

    public function getProjectDirectory(): string
    {
        return $this->projectDirectory;
    }

    /**
     * @return array<string, MarkupKind> Kind by path relative to the project.
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @return list<string> Absolute paths of all files.
     */
    public function getAbsoluteFiles(): array
    {
        return array_map(fn(string $file): string => $this->projectDirectory . '/' . $file, array_keys($this->files));
    }

    /**
     * @return list<string> Existing deprecated directories, relative to the project.
     */
    public function getDeprecatedDirectories(): array
    {
        return $this->deprecatedDirectories;
    }

    /**
     * Directories whose file list affects the check, also if they do not exist yet.
     *
     * @return list<string> Absolute paths.
     */
    public function getWatchedDirectories(): array
    {
        return array_map(
            fn(string $directory): string => $this->projectDirectory . '/' . $directory,
            [...array_map(static fn(MarkupKind $kind): string => $kind->getDirectory(), MarkupKind::cases()), ...self::DEPRECATED_DIRECTORIES],
        );
    }
}

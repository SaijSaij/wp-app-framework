<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Asset;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Saij\WpAppFramework\DependencyInjection\NoService;
use SplFileInfo;

/**
 * Finds the scripts in a consumer's `assets/` directory during the build:
 * document views (assets/**\/*.view.js, loaded on every page of the front
 * end), editor scripts without a block (assets/**\/*.editor.js, loaded in the
 * block editor) and shared modules (assets/shared/**\/*.js, loaded as
 * dependencies of the scripts that import them as `#shared/<name>`). The
 * webpack helper builds each to the same path below `build/`.
 *
 * @internal
 *
 * @phpstan-type ScriptsData array{documentViews: list<string>, editorScripts: list<string>, shared: array<string, string>}
 */
#[NoService]
final class AssetScripts
{
    /** Directory of the shared modules below the asset directory. */
    public const string SHARED_DIRECTORY = 'shared';

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param non-empty-string|null $slug Slug of the project, for the check of the script handles; null for fixtures.
     */
    public function __construct(
        private readonly ?string $slug = null,
    ) {}

    /**
     * @param string|null $assetDirectory The consumer's `assets/`; null for fixtures.
     * @return ScriptsData Scripts below the build directory without extension (e.g. assets/menu.view), sorted;
     *                     shared modules by name (e.g. ui/modal => assets/shared/ui/modal).
     */
    public function collect(?string $assetDirectory): array
    {
        $scripts = ['documentViews' => [], 'editorScripts' => [], 'shared' => []];
        if ($assetDirectory === null || !is_dir($assetDirectory)) {
            return $scripts;
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($assetDirectory, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($assetDirectory) + 1));
            }
        }
        sort($files, SORT_STRING);

        $shared = self::SHARED_DIRECTORY . '/';
        foreach ($files as $file) {
            $script = AssetDirectory::NAME . '/' . substr($file, 0, -strlen('.js'));
            if (str_ends_with($file, ScriptKind::View->value)) {
                $scripts['documentViews'][] = $script;
            } elseif (str_ends_with($file, ScriptKind::Editor->value)) {
                $scripts['editorScripts'][] = $script;
            } elseif (str_starts_with($file, $shared) && str_ends_with($file, '.js')) {
                $scripts['shared'][substr($file, strlen($shared), -strlen('.js'))] = $script;
            }
        }

        if ($this->slug !== null) {
            $this->checkHandles($scripts, $this->slug);
        }

        return $scripts;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Two scripts whose names differ only in case or separators would get the same script handle.
     *
     * @param ScriptsData      $scripts
     * @param non-empty-string $slug
     */
    private function checkHandles(array $scripts, string $slug): void
    {
        $handles = [];
        foreach ($scripts['documentViews'] as $script) {
            $handles[ScriptKind::View->documentHandle($slug, $script)][] = $script . '.js';
        }
        foreach ($scripts['editorScripts'] as $script) {
            $handles[ScriptKind::Editor->documentHandle($slug, $script)][] = $script . '.js';
        }
        foreach ($scripts['shared'] as $name => $script) {
            $handles[ScriptKind::sharedHandle($slug, $name)][] = $script . '.js';
        }
        foreach ($handles as $handle => $files) {
            if (count($files) > 1) {
                $this->errors[] = sprintf('The scripts %s get the same script handle %s; rename one of them.', implode(' and ', $files), $handle);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Release;

use WebCodingCat\WpAppFramework\Asset\AssetDirectory;
use WebCodingCat\WpAppFramework\Asset\ImageVariants;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Editor\EditorAssets;
use WebCodingCat\WpAppFramework\Instance;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Post\MetaBoxes;
use WebCodingCat\WpAppFramework\Setting\SettingsPage;
use WebCodingCat\WpAppFramework\Style\StyleAssets;
use WebCodingCat\WpAppFramework\View\ViewAssets;

/**
 * Checks that the build directory of a release holds every built file the
 * framework loads at run time according to the manifest: the editor runtime
 * and canvas styles for blocks, the editor and view scripts of blocks and
 * block supports, the scripts in the asset directory (document views, editor
 * scripts, shared modules), the view runtime, the settings page, the meta
 * boxes and the stylesheet. Without the check a release without its scripts would fail
 * only on the page that needs them (BuildAssets::asset()).
 *
 * @internal
 *
 * @phpstan-import-type ManifestData from Manifest
 */
#[NoService]
final class BuildCheck
{
    /**
     * The missing built files, relative to the project, each with what needs it.
     *
     * @param ManifestData $manifest
     * @return list<string>
     */
    public static function missing(array $manifest, string $projectDirectory): array
    {
        $required = [];
        $blocks = $manifest['blocks'];
        $meta = $manifest['meta'];
        if ($blocks !== [] || $meta !== []) {
            $required[EditorAssets::RUNTIME . '.asset.php'] = 'editor runtime (blocks, post meta or editor scripts)';
        }
        if ($blocks !== []) {
            $required[EditorAssets::CANVAS . '.asset.php'] = 'canvas styles of the editor (blocks)';
        }
        $views = false;
        foreach ($blocks as $block) {
            if ($block['editor']['editorScript'] !== null) {
                $required[$block['editor']['editorScript'] . '.asset.php'] = sprintf('editor script of block %s', $block['name']);
            }
            if ($block['viewScript'] !== null) {
                $required[$block['viewScript'] . '.asset.php'] = sprintf('view script of block %s', $block['name']);
                $views = true;
            }
            foreach ($block['supports'] as $support) {
                if ($support['editorScript'] !== null) {
                    $required[$support['editorScript'] . '.asset.php'] = sprintf('editor script of block support %s', $support['class']);
                }
                if ($support['viewScript'] !== null) {
                    $required[$support['viewScript'] . '.asset.php'] = sprintf('view script of block support %s', $support['class']);
                    $views = true;
                }
            }
        }
        $scripts = $manifest['scripts'];
        foreach ($scripts['documentViews'] as $script) {
            $required[$script . '.asset.php'] = 'document view';
            $views = true;
        }
        foreach ($scripts['editorScripts'] as $script) {
            $required[$script . '.asset.php'] = 'editor script without a block';
            $required[EditorAssets::RUNTIME . '.asset.php'] = 'editor runtime (blocks, post meta or editor scripts)';
        }
        foreach ($scripts['shared'] as $name => $script) {
            $required[$script . '.asset.php'] = sprintf('shared module #shared/%s', $name);
        }
        if ($views) {
            $required[ViewAssets::RUNTIME . '.asset.php'] = 'view runtime (view scripts)';
        }
        if ($manifest['settings']['groups'] !== []) {
            $required[SettingsPage::SCRIPT . '.asset.php'] = 'settings page (settings groups)';
        }
        foreach ($manifest['settings']['groups'] as $group) {
            if ($group['script'] !== null) {
                $required[$group['script'] . '.asset.php'] = sprintf('settings script of settings group %s', $group['name']);
            }
        }
        if ($meta !== []) {
            $required[MetaBoxes::SCRIPT . '.asset.php'] = 'meta boxes (post meta)';
        }
        if ($manifest['styles']) {
            $required[StyleAssets::BUILD . '.css'] = 'stylesheet (' . AssetDirectory::NAME . '/' . StyleAssets::SOURCE . ')';
        }
        if ($manifest['stylePreload']) {
            $required[StyleAssets::PRELOAD] = 'fonts marked for preloading in ' . AssetDirectory::NAME . '/' . StyleAssets::SOURCE;
        }

        $missing = [];
        foreach ($required as $file => $reason) {
            $path = Instance::BUILD_DIRECTORY . '/' . $file;
            if (!is_file($projectDirectory . '/' . $path)) {
                $missing[] = sprintf('%s (%s)', $path, $reason);
            }
        }
        foreach (array_keys(ImageVariants::files($manifest['assets'])) as $file) {
            $path = 'generated/' . ImageVariants::DIRECTORY . '/' . $file;
            if (!is_file($projectDirectory . '/' . $path)) {
                $missing[] = sprintf('%s (image variant)', $path);
            }
        }
        foreach (array_keys($manifest['assets']) as $name) {
            $path = AssetDirectory::NAME . '/' . $name;
            if (!is_file($projectDirectory . '/' . $path)) {
                $missing[] = sprintf('%s (static asset, e.g. removed by export-ignore)', $path);
            }
        }

        return $missing;
    }

    /**
     * @param ManifestData $manifest
     * @throws ReleaseException If built files are missing.
     */
    public static function check(array $manifest, string $projectDirectory): void
    {
        $missing = self::missing($manifest, $projectDirectory);
        if ($missing === []) {
            return;
        }

        throw new ReleaseException(sprintf(
            "The release lacks built files the framework loads at run time:\n- %s\n%s",
            implode("\n- ", $missing),
            is_file($projectDirectory . '/package.json')
                ? 'Check that "npm run build" builds them (webpack.config.js with the webpack helper of the framework).'
                : 'The project has no package.json, so nothing was built: add a package.json with a "build" script and a webpack.config.js with the webpack helper of the framework.',
        ));
    }
}

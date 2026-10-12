<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Editor;

use Saij\WpAppFramework\Asset\BuildAssets;
use Saij\WpAppFramework\Asset\ScriptKind;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\View\ViewAssets;

/**
 * Loads the editor runtime and the block editor scripts of one framework
 * instance (on enqueue_block_editor_assets).
 *
 * The consumer's build compiles both with the framework's webpack helper into
 * its `build/` directory: the runtime to build/vendor/wp-app-framework/editor.js
 * with its styles (editor.css, for the inspector controls of the framework),
 * block scripts mirrored from `src/` (src/Block/Hero/Hero.editor.js →
 * build/Block/Hero/Hero.editor.js), each with an .asset.php file.
 *
 * The view scripts of blocks and block supports load with the runtime as
 * well: the runtime mounts them on a block in preview mode.
 *
 * @internal
 *
 * @phpstan-import-type BlockEntry from Manifest
 */
#[NoService]
final readonly class EditorAssets
{
    /** The runtime below the build directory, without extension. */
    public const string RUNTIME = 'vendor/wp-app-framework/editor';

    /** Styles of the editor canvas below the build directory, without extension. */
    public const string CANVAS = 'vendor/wp-app-framework/canvas';

    private BuildAssets $assets;

    private ViewAssets $views;

    /**
     * @param string           $slug           Slug of the framework instance; names the script handles and the JS global.
     * @param string           $buildDirectory Filesystem path of the consumer's build directory.
     * @param string           $buildUri       URL of the consumer's build directory.
     * @param list<BlockEntry> $blocks
     * @param bool             $development    Development mode: the runtime warns about template mistakes.
     */
    public function __construct(
        private string $slug,
        string $buildDirectory,
        string $buildUri,
        private array $blocks,
        private bool $development,
    ) {
        $this->assets = new BuildAssets($slug, $buildDirectory, $buildUri);
        $this->views = new ViewAssets($slug, $this->assets);
    }

    /**
     * @return non-empty-string
     */
    public static function runtimeHandle(string $slug): string
    {
        return $slug . '-wp-app-framework';
    }

    /**
     * Without blocks the instance needs no editor runtime, so a theme without
     * blocks needs no script build either.
     */
    public function enqueue(): void
    {
        if ($this->blocks === []) {
            return;
        }

        $handle = self::runtimeHandle($this->slug);
        $version = $this->assets->registerScript($handle, self::RUNTIME);
        wp_add_inline_script($handle, $this->configScript(), 'before');
        wp_enqueue_script($handle);

        $this->assets->registerStyle($handle, self::RUNTIME, ['wp-components'], $version);
        wp_enqueue_style($handle);

        foreach ($this->blocks as $block) {
            $script = $block['editor']['editorScript'];
            if ($script === null) {
                continue;
            }
            $blockHandle = ScriptKind::Editor->blockHandle($this->slug, $block['name']);
            $this->assets->registerScript($blockHandle, $script);
            wp_enqueue_script($blockHandle);
        }

        // A support's editor script once, if any block uses the support.
        $supportScripts = [];
        foreach ($this->blocks as $block) {
            foreach ($block['supports'] as $support) {
                if ($support['editorScript'] !== null) {
                    $supportScripts[$support['attribute']] = $support['editorScript'];
                }
            }
        }
        foreach ($supportScripts as $attribute => $script) {
            $supportHandle = ScriptKind::Editor->supportHandle($this->slug, $attribute);
            $this->assets->registerScript($supportHandle, $script);
            wp_enqueue_script($supportHandle);
        }

        foreach ($this->blocks as $block) {
            $this->views->enqueue($block);
        }
    }

    /**
     * Loads the styles of the editor canvas (outlines of the fields edited in
     * the canvas, loading state) on enqueue_block_assets, which WordPress also
     * runs to collect the assets of the canvas iframe. Only in the editor,
     * never on the front end; not for an instance without blocks.
     */
    public function enqueueCanvas(): void
    {
        if (!is_admin() || $this->blocks === []) {
            return;
        }

        $handle = self::runtimeHandle($this->slug) . '-canvas';
        $this->assets->registerStyle($handle, self::CANVAS, [], $this->assets->asset(self::CANVAS)['version']);
        wp_enqueue_style($handle);
    }

    /**
     * The instance configuration: the JS global `wpAppFramework[slug]`, which the runtime extends with its API.
     */
    public function configScript(): string
    {
        $blocks = [];
        foreach ($this->blocks as $block) {
            $blocks[$block['name']] = [
                'fields' => (object) $block['editor']['fields'],
                'controls' => (object) $block['editor']['controls'],
                'innerBlocks' => $block['editor']['innerBlocks'],
                'postContentField' => $block['editor']['postContentField'],
                'supports' => $block['editor']['supports'],
                'view' => ViewAssets::hasViews($block),
            ];
        }

        $config = [
            'slug' => $this->slug,
            'renderPath' => '/' . RenderController::NAMESPACE . RenderController::route($this->slug),
            'previewPath' => '/' . RenderController::NAMESPACE . RenderController::previewRoute($this->slug),
            'maxBlocks' => RenderController::MAX_BLOCKS,
            'development' => $this->development,
            'blocks' => (object) $blocks,
        ];

        return sprintf(
            'window.wpAppFramework = window.wpAppFramework || {}; window.wpAppFramework[%s] = { config: %s };',
            wp_json_encode($this->slug),
            wp_json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP),
        );
    }
}

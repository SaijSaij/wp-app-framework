<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Editor;

use LogicException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Manifest\Manifest;

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

    /**
     * @param string           $slug           Slug of the framework instance; names the script handles and the JS global.
     * @param string           $buildDirectory Filesystem path of the consumer's build directory.
     * @param string           $buildUri       URL of the consumer's build directory.
     * @param list<BlockEntry> $blocks
     * @param bool             $development    Development mode: the runtime warns about template mistakes.
     */
    public function __construct(
        private string $slug,
        private string $buildDirectory,
        private string $buildUri,
        private array $blocks,
        private bool $development,
    ) {}

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
        $version = $this->register($handle, self::RUNTIME);
        wp_add_inline_script($handle, $this->configScript(), 'before');
        wp_enqueue_script($handle);

        $this->registerStyle($handle, self::RUNTIME, ['wp-components'], $version);
        wp_enqueue_style($handle);

        foreach ($this->blocks as $block) {
            $script = $block['editor']['editorScript'];
            if ($script === null) {
                continue;
            }
            $blockHandle = $this->slug . '-' . str_replace('/', '-', $block['name']) . '-editor';
            $this->register($blockHandle, $script);
            wp_enqueue_script($blockHandle);
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
        $this->registerStyle($handle, self::CANVAS, [], $this->asset(self::CANVAS)['version']);
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
            ];
        }

        $config = [
            'slug' => $this->slug,
            'renderPath' => '/' . RenderController::NAMESPACE . RenderController::route($this->slug),
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

    /**
     * @param non-empty-string $handle
     * @return string The version from the asset file.
     */
    private function register(string $handle, string $script): string
    {
        $asset = $this->asset($script);
        wp_register_script($handle, $this->buildUri . '/' . $script . '.js', $asset['dependencies'], $asset['version'], ['in_footer' => false]);

        return $asset['version'];
    }

    /**
     * @return array{dependencies: list<non-empty-string>, version: string}
     */
    private function asset(string $script): array
    {
        $assetFile = $this->buildDirectory . '/' . $script . '.asset.php';
        if (!is_file($assetFile)) {
            throw new LogicException(sprintf(
                'The editor script %s is not built: %s is missing. Run "npm run build" (or "npm run start") in the theme.',
                $script,
                $assetFile,
            ));
        }

        $asset = require $assetFile;
        if (!is_array($asset) || !is_array($asset['dependencies'] ?? null) || !is_string($asset['version'] ?? null)) {
            throw new LogicException(sprintf('%s does not return an array with "dependencies" and "version".', $assetFile));
        }

        /** @var array{dependencies: list<non-empty-string>, version: string} $asset */
        return $asset;
    }

    /**
     * Registers the built styles {name}.css; the build writes {name}-rtl.css
     * next to it, which WordPress swaps in for right-to-left languages.
     *
     * @param list<string> $dependencies
     */
    private function registerStyle(string $handle, string $name, array $dependencies, string $version): void
    {
        $file = $this->buildDirectory . '/' . $name . '.css';
        if (!is_file($file)) {
            throw new LogicException(sprintf(
                'The styles %s are not built: %s is missing. Run "npm run build" (or "npm run start") in the theme.',
                $name,
                $file,
            ));
        }
        wp_register_style($handle, $this->buildUri . '/' . $name . '.css', $dependencies, $version);
        wp_style_add_data($handle, 'rtl', 'replace');
    }
}

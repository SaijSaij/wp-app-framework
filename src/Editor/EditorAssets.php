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
 * its `build/` directory: the runtime to build/vendor/wp-app-framework/editor.js,
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
    ) {
    }

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
        $this->register($handle, self::RUNTIME);
        wp_add_inline_script($handle, $this->configScript(), 'before');
        wp_enqueue_script($handle);

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

    private function register(string $handle, string $script): void
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

        wp_register_script($handle, $this->buildUri . '/' . $script . '.js', $asset['dependencies'], $asset['version'], ['in_footer' => false]);
    }
}

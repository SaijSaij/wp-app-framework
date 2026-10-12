<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Asset;

use LogicException;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Registers scripts and styles from a consumer's build directory: each script
 * {name}.js comes with {name}.asset.php (dependencies and version, written by
 * the build), each stylesheet {name}.css with {name}-rtl.css.
 *
 * @internal
 */
#[NoService]
final readonly class BuildAssets
{
    /**
     * @param string $slug           Slug of the framework instance, for error messages.
     * @param string $buildDirectory Filesystem path of the consumer's build directory.
     * @param string $buildUri       URL of the consumer's build directory.
     */
    public function __construct(
        private string $slug,
        private string $buildDirectory,
        private string $buildUri,
    ) {}

    /**
     * @param non-empty-string                                $handle
     * @param string                                          $script Below the build directory, without extension.
     * @param array{in_footer?: bool, strategy?: 'defer'|'async'} $args   Loading options of wp_register_script().
     * @return string The version from the asset file.
     */
    public function registerScript(string $handle, string $script, array $args = ['in_footer' => false]): string
    {
        $asset = $this->asset($script);
        wp_register_script($handle, $this->buildUri . '/' . $script . '.js', $asset['dependencies'], $asset['version'], $args);

        return $asset['version'];
    }

    /**
     * Dependencies and version of a built script.
     *
     * @return array{dependencies: list<non-empty-string>, version: string}
     */
    public function asset(string $script): array
    {
        $assetFile = $this->buildDirectory . '/' . $script . '.asset.php';
        if (!is_file($assetFile)) {
            throw new LogicException(sprintf(
                'The script %s is not built: %s is missing. Run "npm run build" (or "npm run start") in the theme or plugin %s.',
                $script,
                $assetFile,
                $this->slug,
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
     * @param non-empty-string $handle
     * @param list<string>     $dependencies
     */
    public function registerStyle(string $handle, string $name, array $dependencies, string $version): void
    {
        $file = $this->buildDirectory . '/' . $name . '.css';
        if (!is_file($file)) {
            throw new LogicException(sprintf(
                'The styles %s are not built: %s is missing. Run "npm run build" (or "npm run start") in the theme or plugin %s.',
                $name,
                $file,
                $this->slug,
            ));
        }
        wp_register_style($handle, $this->buildUri . '/' . $name . '.css', $dependencies, $version);
        wp_style_add_data($handle, 'rtl', 'replace');
    }
}

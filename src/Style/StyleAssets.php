<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Style;

use LogicException;
use Saij\WpAppFramework\Asset\AssetDirectory;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Loads the consumer's stylesheet on the front end and in the editor canvas.
 *
 * The consumer's build compiles `assets/styles.css` with Tailwind into
 * `build/styles.css` (webpack helper of the framework); the manifest records
 * whether the consumer has that file. WordPress runs enqueue_block_assets on
 * the front end and again to collect the assets of the editor iframes, with
 * should_load_block_editor_scripts_and_styles forced to false
 * (_wp_get_iframed_editor_assets() in wp-includes/block-editor.php). The
 * editor's main document runs it as well (wp_common_block_scripts_and_styles());
 * the stylesheet stays out of it, so it never styles the admin interface.
 *
 * @internal
 */
#[NoService]
final readonly class StyleAssets
{
    /** The stylesheet below the consumer's asset directory (`assets/`). */
    public const string SOURCE = 'styles.css';

    /** Where the stylesheet was until 0.13, below the script directory (`src/`); now a build error. */
    public const string FORMER_SOURCE = 'styles.css';

    /** The built stylesheet below the build directory, without extension. */
    public const string BUILD = 'styles';

    /**
     * @param string $slug           Slug of the framework instance; names the style handle.
     * @param string $buildDirectory Filesystem path of the consumer's build directory.
     * @param string $buildUri       URL of the consumer's build directory.
     */
    public function __construct(
        private string $slug,
        private string $buildDirectory,
        private string $buildUri,
    ) {}

    /**
     * The build error for a stylesheet in its former place, `src/styles.css`, which the framework no longer builds.
     *
     * @param string|null $scriptDirectory The consumer's `src/`; null without one.
     * @return list<string>
     */
    public static function sourceErrors(?string $scriptDirectory): array
    {
        if ($scriptDirectory === null || !is_file($scriptDirectory . '/' . self::FORMER_SOURCE)) {
            return [];
        }

        return [sprintf(
            'The stylesheet %s is no longer built: move it to %s/%s in the root of the theme or plugin and make the paths in it relative to that directory (e.g. @source "../src").',
            $scriptDirectory . '/' . self::FORMER_SOURCE,
            AssetDirectory::NAME,
            self::SOURCE,
        )];
    }

    /**
     * @return non-empty-string
     */
    public static function handle(string $slug): string
    {
        return $slug . '-styles';
    }

    public function enqueue(): void
    {
        if (is_admin() && wp_should_load_block_editor_scripts_and_styles()) {
            return;
        }

        $file = $this->buildDirectory . '/' . self::BUILD . '.css';
        $assetFile = $this->buildDirectory . '/' . self::BUILD . '.asset.php';
        foreach ([$file, $assetFile] as $required) {
            if (!is_file($required)) {
                throw new LogicException(sprintf(
                    'The styles of %s/%s are not built: %s is missing. Run "npm run build" (or "npm run start") in the theme or plugin %s.',
                    AssetDirectory::NAME,
                    self::SOURCE,
                    $required,
                    $this->slug,
                ));
            }
        }

        $asset = require $assetFile;
        if (!is_array($asset) || !is_string($asset['version'] ?? null)) {
            throw new LogicException(sprintf('%s does not return an array with "version".', $assetFile));
        }

        // No right-to-left replacement: Tailwind handles direction with logical properties and rtl: variants.
        $handle = self::handle($this->slug);
        wp_register_style($handle, $this->buildUri . '/' . self::BUILD . '.css', [], $asset['version']);
        wp_enqueue_style($handle);
    }
}

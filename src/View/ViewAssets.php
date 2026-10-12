<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\View;

use Saij\WpAppFramework\Asset\BuildAssets;
use Saij\WpAppFramework\Asset\ScriptKind;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Manifest\Manifest;

/**
 * Loads the view scripts (front-end scripts) of one framework instance.
 *
 * The consumer's build compiles each view script next to a block or block
 * support class (src/Block/Faq/Faq.view.js → build/Block/Faq/Faq.view.js) and
 * the view runtime of the framework (build/vendor/wp-app-framework/view.js),
 * which provides the base classes and mounts the views. A script is
 * registered the first time a block needs it, so only pages that render such
 * a block read its asset file and load it, and a missing build only fails
 * there.
 *
 * @internal
 *
 * @phpstan-import-type BlockEntry from Manifest
 */
#[NoService]
final readonly class ViewAssets
{
    /** The view runtime below the build directory, without extension. */
    public const string RUNTIME = 'vendor/wp-app-framework/view';

    /**
     * Attribute of the root element of a block with view scripts: JSON with the
     * slug of the instance and the views to mount, e.g.
     * {"slug":"my-theme","views":[{"view":"Block/Faq/Faq.view"},{"view":"Support/Reveal.view","value":true}]}.
     * The view runtime reads it (assets/view/).
     */
    public const string MARKER = 'data-wp-app-framework-views';

    /** Loading options: deferred in the footer, so the views mount after the document has been parsed. */
    private const array ARGS = ['in_footer' => true, 'strategy' => 'defer'];

    /**
     * @param string $slug Slug of the framework instance; names the script handles.
     */
    public function __construct(
        private string $slug,
        private BuildAssets $assets,
    ) {}

    /**
     * @return non-empty-string
     */
    public static function runtimeHandle(string $slug): string
    {
        return $slug . '-wp-app-framework-view';
    }

    /**
     * Whether the block or one of its block supports has a view script.
     *
     * @param BlockEntry $block
     */
    public static function hasViews(array $block): bool
    {
        if ($block['viewScript'] !== null) {
            return true;
        }
        foreach ($block['supports'] as $support) {
            if ($support['viewScript'] !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * The marker attribute of a rendered block: its view script, then the view
     * scripts of its block supports with their values.
     *
     * @param BlockEntry           $block
     * @param array<string, mixed> $supportValues Sanitized value of each support attribute.
     * @return array<string, string>
     */
    public function marker(array $block, array $supportValues): array
    {
        $views = [];
        if ($block['viewScript'] !== null) {
            $views[] = ['view' => $block['viewScript']];
        }
        foreach ($block['supports'] as $support) {
            if ($support['viewScript'] !== null) {
                $views[] = ['view' => $support['viewScript'], 'value' => $supportValues[$support['attribute']] ?? null];
            }
        }

        return [self::MARKER => (string) wp_json_encode(['slug' => $this->slug, 'views' => $views], JSON_UNESCAPED_SLASHES)];
    }

    /**
     * Enqueues the view runtime and the view scripts of a block and of its block supports.
     *
     * @param BlockEntry $block
     */
    public function enqueue(array $block): void
    {
        foreach ($this->scripts($block) as $handle => $script) {
            if (!wp_script_is($handle, 'registered')) {
                $this->assets->registerScript($handle, $script, self::ARGS);
            }
            wp_enqueue_script($handle);
        }
    }

    /**
     * @param BlockEntry $block
     * @return array<non-empty-string, string> Script below the build directory by handle; empty without view scripts.
     */
    private function scripts(array $block): array
    {
        $scripts = [];
        if ($block['viewScript'] !== null) {
            $scripts[ScriptKind::View->blockHandle($this->slug, $block['name'])] = $block['viewScript'];
        }
        foreach ($block['supports'] as $support) {
            if ($support['viewScript'] !== null) {
                $scripts[ScriptKind::View->supportHandle($this->slug, $support['attribute'])] = $support['viewScript'];
            }
        }

        return $scripts === [] ? [] : [self::runtimeHandle($this->slug) => self::RUNTIME, ...$scripts];
    }
}

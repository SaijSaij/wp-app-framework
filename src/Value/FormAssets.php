<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Value;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Loads what the form controls of values need outside the block editor's own
 * components: the media library for images, the classic editor for rich text,
 * the color palette of the theme for colors.
 *
 * @internal
 */
#[NoService]
final class FormAssets
{
    /**
     * @param array<mixed> $controls Controls (ValueSchemaBuilder), also nested in value classes and lists.
     * @param string       $handle   The script of the form, which gets the palette before it.
     * @param string       $slug     The slug of the instance: the palette is `wpAppFramework[slug].palette`.
     */
    public static function enqueue(array $controls, string $handle, string $slug): void
    {
        if (self::hasControl($controls, 'image')) {
            wp_enqueue_media();
        }
        if (self::hasControl($controls, 'rich')) {
            wp_enqueue_editor();
        }
        if (self::hasControl($controls, 'color')) {
            wp_add_inline_script($handle, sprintf(
                'window.wpAppFramework = window.wpAppFramework || {}; window.wpAppFramework[%1$s] = window.wpAppFramework[%1$s] || {}; window.wpAppFramework[%1$s].palette = %2$s;',
                wp_json_encode($slug),
                wp_json_encode(self::palette(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP),
            ), 'before');
        }
    }

    /**
     * The hex colors of the palette as the block editor offers them (useSettings('color.palette') in
     * block-editor's getBlockSettings()): the colors of the site editor if there are any, else those of the theme,
     * else the default colors of WordPress. Other values, such as CSS variables, cannot be stored by #[Color].
     *
     * @return list<array{name: string, color: string}>
     */
    public static function palette(): array
    {
        $origins = wp_get_global_settings(['color', 'palette']);
        $palette = is_array($origins) ? $origins['custom'] ?? $origins['theme'] ?? $origins['default'] ?? [] : [];
        $colors = [];
        foreach (is_array($palette) ? $palette : [] as $entry) {
            $color = is_array($entry) ? $entry['color'] ?? null : null;
            if (!is_string($color) || $color === '' || !Color::isColor($color)) {
                continue;
            }
            $name = $entry['name'] ?? null;
            $colors[] = ['name' => is_string($name) && $name !== '' ? $name : $color, 'color' => $color];
        }

        return $colors;
    }

    /**
     * Whether controls contain a control of the type, also in value classes and lists.
     *
     * @param array<mixed> $controls
     */
    public static function hasControl(array $controls, string $type): bool
    {
        foreach ($controls as $key => $value) {
            if ($key === 'type' && $value === $type) {
                return true;
            }
            if (is_array($value) && self::hasControl($value, $type)) {
                return true;
            }
        }

        return false;
    }
}

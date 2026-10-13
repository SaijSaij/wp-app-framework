<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Value;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Loads what the form controls of values need outside the block editor's own
 * components: the media library for images, the classic editor for rich text.
 *
 * @internal
 */
#[NoService]
final class FormAssets
{
    /**
     * @param array<mixed> $controls Controls (ValueSchemaBuilder), also nested in value classes and lists.
     */
    public static function enqueue(array $controls): void
    {
        if (self::hasControl($controls, 'image')) {
            wp_enqueue_media();
        }
        if (self::hasControl($controls, 'rich')) {
            wp_enqueue_editor();
        }
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

<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Support;

use LogicException;
use WP_Block;

/**
 * Typed access to what the WordPress test doubles (tests/Stubs/wordpress.php) recorded in $GLOBALS.
 */
final class Recorded
{
    /**
     * @return array<string, array<string, mixed>> Arguments of register_block_type() by block name.
     */
    public static function blocks(): array
    {
        /** @var array<string, array<string, mixed>> */
        return $GLOBALS['wp_app_framework_test_blocks'] ?? [];
    }

    /**
     * @return \Closure(array<string, mixed>, string=, WP_Block=): string
     */
    public static function renderCallback(string $block): \Closure
    {
        $callback = self::blocks()[$block]['render_callback'] ?? null;
        if (!$callback instanceof \Closure) {
            throw new LogicException(sprintf('Block %s was registered without a render callback.', $block));
        }

        /** @var \Closure(array<string, mixed>, string=, WP_Block=): string */
        return $callback;
    }

    /**
     * @return array<string, array{src: string, deps: list<string>, ver: string|bool|null, enqueued: bool, before: list<string>}> By handle.
     */
    public static function scripts(): array
    {
        /** @var array<string, array{src: string, deps: list<string>, ver: string|bool|null, enqueued: bool, before: list<string>}> */
        return $GLOBALS['wp_app_framework_test_scripts'] ?? [];
    }

    /**
     * @return array<string, array<string, mixed>> By handle.
     */
    public static function styles(): array
    {
        /** @var array<string, array<string, mixed>> */
        return $GLOBALS['wp_app_framework_test_styles'] ?? [];
    }
}

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
     * @return list<callable(list<string>): list<string>> Callbacks add_filter() recorded for a filter on lists of names.
     */
    public static function listFilters(string $name): array
    {
        /** @var array<string, list<callable(list<string>): list<string>>> $filters */
        $filters = $GLOBALS['wp_app_framework_test_filters'] ?? [];

        return $filters[$name] ?? [];
    }

    /**
     * @phpstan-impure Changes with each registration.
     * @return array<string, array{src: string, deps: list<string>, ver: string|bool|null, args: array<string, mixed>|bool, enqueued: bool, before: list<string>}> By handle.
     */
    public static function scripts(): array
    {
        /** @var array<string, array{src: string, deps: list<string>, ver: string|bool|null, args: array<string, mixed>|bool, enqueued: bool, before: list<string>}> */
        return $GLOBALS['wp_app_framework_test_scripts'] ?? [];
    }

    /**
     * @phpstan-impure Changes with each registration.
     * @return array<string, array<string, mixed>> By handle.
     */
    public static function styles(): array
    {
        /** @var array<string, array<string, mixed>> */
        return $GLOBALS['wp_app_framework_test_styles'] ?? [];
    }

    /**
     * @phpstan-impure Changes with each registration.
     * @return array<string, array<string, mixed>> Arguments of register_setting() (plus "group") by option name.
     */
    public static function settings(): array
    {
        /** @var array<string, array<string, mixed>> */
        return $GLOBALS['wp_app_framework_test_settings'] ?? [];
    }

    /**
     * @phpstan-impure Changes with each registration.
     * @return array<string, array<string, mixed>> Admin pages added with add_theme_page() or add_options_page(), by menu slug.
     */
    public static function pages(): array
    {
        /** @var array<string, array<string, mixed>> */
        return $GLOBALS['wp_app_framework_test_pages'] ?? [];
    }
}

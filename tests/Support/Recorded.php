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
     * @return list<array<string, mixed>> Arguments of WP_Query::query() in the order of the queries.
     */
    public static function queries(): array
    {
        /** @var list<array<string, mixed>> */
        return $GLOBALS['wp_app_framework_test_queries'] ?? [];
    }

    /**
     * @return list<callable> Callbacks added to a filter or action and not removed.
     */
    public static function filters(string $hook): array
    {
        /** @var array<string, array<int, callable>> $filters */
        $filters = $GLOBALS['wp_app_framework_test_filters'] ?? [];

        return array_values($filters[$hook] ?? []);
    }

    /**
     * @return list<array<string, string>> SQL clauses of the queries after the filter posts_clauses.
     */
    public static function queryClauses(): array
    {
        /** @var list<array<string, string>> */
        return $GLOBALS['wp_app_framework_test_query_clauses'] ?? [];
    }

    /**
     * @return list<list<int>> IDs passed to _prime_post_caches().
     */
    public static function primed(): array
    {
        /** @var list<list<int>> */
        return $GLOBALS['wp_app_framework_test_primed'] ?? [];
    }

    /**
     * @return array<string, array<string, mixed>> Arguments of register_block_type() by block name.
     */
    public static function blocks(): array
    {
        /** @var array<string, array<string, mixed>> */
        return $GLOBALS['wp_app_framework_test_blocks'] ?? [];
    }

    /**
     * @return array<string, array<string, mixed>> Meta boxes added with add_meta_box() by ID.
     */
    public static function metaBoxes(): array
    {
        /** @var array<string, array<string, mixed>> */
        return $GLOBALS['wp_app_framework_test_meta_boxes'] ?? [];
    }

    /**
     * @return array<string, array<string, mixed>> Arguments of register_post_type() by post type.
     */
    public static function postTypes(): array
    {
        /** @var array<string, array<string, mixed>> */
        return $GLOBALS['wp_app_framework_test_post_types'] ?? [];
    }

    /**
     * @return array<string, array{list<string>, array<string, mixed>}> Object types and arguments of register_taxonomy().
     */
    public static function taxonomies(): array
    {
        /** @var array<string, array{list<string>, array<string, mixed>}> */
        return $GLOBALS['wp_app_framework_test_taxonomies'] ?? [];
    }

    /**
     * @return array<string, array<string, array<string, mixed>>> Arguments of register_post_meta() by post type and key.
     */
    public static function registeredMeta(): array
    {
        /** @var array<string, array<string, array<string, mixed>>> */
        return $GLOBALS['wp_app_framework_test_registered_meta'] ?? [];
    }

    /**
     * The callback of a meta box added with add_meta_box().
     *
     * @return callable(\WP_Post): mixed
     */
    public static function metaBoxCallback(string $id): callable
    {
        $callback = self::metaBoxes()[$id]['callback'] ?? null;
        if (!is_callable($callback)) {
            throw new LogicException(sprintf('No meta box %s was added.', $id));
        }

        return $callback;
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

    /**
     * @phpstan-impure Changes with each write.
     * @return array<string, array{value: mixed, expiration: int}> Transients by name.
     */
    public static function transients(): array
    {
        /** @var array<string, array{value: mixed, expiration: int}> */
        return $GLOBALS['wp_app_framework_test_transients'] ?? [];
    }

    /**
     * @phpstan-impure Changes with each write.
     * @return array<string, array{data: mixed, expire: int}> Entries of the object cache group by key.
     */
    public static function objectCache(string $group): array
    {
        /** @var array<string, array<string, array{data: mixed, expire: int}>> $cache */
        $cache = $GLOBALS['wp_app_framework_test_object_cache'] ?? [];

        return $cache[$group] ?? [];
    }

    /**
     * @phpstan-impure Changes with each write.
     * @return array<string, mixed> Options by name.
     */
    public static function options(): array
    {
        /** @var array<string, mixed> */
        return $GLOBALS['wp_app_framework_test_options'] ?? [];
    }

    /**
     * @phpstan-impure Changes with each registration.
     * @return array<string, array{callable: callable|object|string, args: array<string, mixed>}> Commands passed to
     *         WP_CLI::add_command() (tests/Stubs/wp-cli.php) by name, in registration order.
     */
    public static function cliCommands(): array
    {
        /** @var array<string, array{callable: callable|object|string, args: array<string, mixed>}> */
        return $GLOBALS['wp_app_framework_test_cli_commands'] ?? [];
    }

    /**
     * @phpstan-impure Changes with each message.
     * @return list<list<mixed>> Messages of WP_CLI::log(), success(), warning() and error() as [type, message], tables
     *         of format_items() as ['format', format, items, fields].
     */
    public static function cliOutput(): array
    {
        /** @var list<list<mixed>> */
        return $GLOBALS['wp_app_framework_test_cli_output'] ?? [];
    }
}

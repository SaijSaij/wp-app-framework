<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Table;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Project\NamePrefix;

/**
 * Names of the tables and options of an instance: tables `{$wpdb->prefix}{namespace}_{name}` – the prefix of the
 * current site, so it is taken on every access –, the option with the state of the schema sync `{slug}_tables` and its
 * lock `{slug}_tables_lock`.
 *
 * @internal
 */
#[NoService]
final class TableNames
{
    /**
     * The full name of a table.
     *
     * @param string           $prefix    The table prefix of the current site ($wpdb->prefix).
     * @param non-empty-string $namespace
     * @param non-empty-string $name      The local name (#[Table]).
     * @return non-empty-string
     */
    public static function full(string $prefix, string $namespace, string $name): string
    {
        return $prefix . NamePrefix::data($namespace) . $name;
    }

    /**
     * The name of the block context that holds the primary key of a row in a repeater (`Block\Repeater::row()`),
     * e.g. `dev-test/log`; `#[Item]` reads it.
     *
     * @param non-empty-string $namespace
     * @param non-empty-string $name      The local name (#[Table]).
     * @return non-empty-string
     */
    public static function context(string $namespace, string $name): string
    {
        return $namespace . '/' . $name;
    }

    /**
     * The option (one per site, autoloaded) with the hash, version and namespace of the last sync and the tables it
     * manages.
     *
     * @param non-empty-string $slug
     * @return non-empty-string
     */
    public static function option(string $slug): string
    {
        return NamePrefix::template($slug) . 'tables';
    }

    /**
     * The option that locks the sync (not autoloaded, written by SQL).
     *
     * @param non-empty-string $slug
     * @return non-empty-string
     */
    public static function lock(string $slug): string
    {
        return self::option($slug) . '_lock';
    }
}

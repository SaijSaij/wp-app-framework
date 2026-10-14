<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Table;

use LogicException;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Which instance a row class belongs to. Row objects load and save themselves (AbstractRow::get(), save()), so they
 * reach the table store of their instance through this registry, filled when an instance boots. The keys are classes
 * of the consumers, unique to one instance. Not a service: nothing outside the framework reads the registry.
 *
 * @internal
 */
#[NoService]
final class TableRegistry
{
    /** @var array<class-string, TableStore> */
    private static array $stores = [];

    public static function register(TableStore $store): void
    {
        foreach (array_keys($store->getClasses()) as $class) {
            self::$stores[$class] = $store;
        }
    }

    /**
     * @param class-string $class
     */
    public static function store(string $class): TableStore
    {
        return self::$stores[$class] ?? throw new LogicException(sprintf(
            '%s is no row class of a booted theme or plugin: row classes need #[Table] and are available once the theme or plugin has booted (rebuild the manifest after code changes).',
            $class,
        ));
    }

    /**
     * Forgets all registrations; for tests only.
     */
    public static function reset(): void
    {
        self::$stores = [];
    }
}

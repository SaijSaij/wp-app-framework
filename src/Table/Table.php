<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Table;

use Attribute;

/**
 * Makes a class that extends AbstractRow the row of a database table of its own:
 *
 *     #[Table('log')]
 *     final class LogEntry extends AbstractRow
 *     {
 *         public function __construct(
 *             #[PrimaryKey] private ?int $id = null,
 *             private string $message = '',
 *         ) {}
 *     }
 *
 * The table is called `{$wpdb->prefix}{namespace}_{name}` (`wp_dev_test_log`); every promoted constructor parameter is
 * a column. The framework creates the table and keeps it in line with the class (see the guide on tables).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Table
{
    /**
     * @param string       $name        Name of the table within the namespace: lowercase letters, digits and "_",
     *                                  starting with a letter.
     * @param list<string> $renamedFrom Earlier names of the table, newest first: an existing table of such a name is
     *                                  renamed instead of creating an empty one.
     */
    public function __construct(
        public string $name,
        public array $renamedFrom = [],
    ) {}
}

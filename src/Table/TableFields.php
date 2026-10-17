<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Table;

use Attribute;

/**
 * Marks a class whose public static methods return the columns of a row class for queries:
 *
 *     #[TableFields(LogEntry::class)]
 *     final class LogEntryFields
 *     {
 *         public static function message(): StringField
 *         {
 *             return new StringField(LogEntry::class, 'message');
 *         }
 *     }
 *
 * Write it by hand or generate it with `wp-app-framework table-fields <row class>`. The build calls every public
 * static method that returns a field and checks the field against the row class. The class is no service.
 *
 * @api Attributes/Tables
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class TableFields
{
    /**
     * @param class-string<AbstractRow> $rowClass The row class (#[Table]).
     */
    public function __construct(
        public string $rowClass,
    ) {}
}

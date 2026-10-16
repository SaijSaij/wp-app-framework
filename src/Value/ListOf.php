<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Value;

use Attribute;

/**
 * Declares the item type of an `array` parameter: a backed enum or a value
 * class (PHP has no generics). Used in block attribute classes, value classes
 * and REST arguments.
 *
 * `min` and `max` limit the number of items editors can create. They are no
 * schema keywords of stored values, so a stored list outside the limits is
 * kept as it is: the editor marks it, and only its first `max` items are
 * created. REST arguments validate them (`minItems`, `maxItems`).
 *
 * @api Attributes/Values
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class ListOf
{
    /**
     * @param class-string $class A backed enum or a value class.
     * @param int          $min   The fewest items: editors cannot remove more. 0 or more.
     * @param int|null     $max   The most items: editors cannot add more, and only the first `max` stored items are
     *                            created. 1 or more and at least `min`; null for no limit.
     */
    public function __construct(
        public string $class,
        public int $min = 0,
        public ?int $max = null,
    ) {}
}

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
 * Editors see each item as a card that they can expand, collapse and drag to
 * sort. Its heading is the first text of the item (the label of an enum
 * case), else `itemLabel` with the number of the item.
 *
 * With `appender`, the block editor shows one more item in the canvas, empty
 * and faded, as long as the list is below `max`: a click adds it. The template
 * marks the root element of each item with `list_item()`.
 *
 * @api Attributes/Values
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class ListOf
{
    /**
     * @param class-string $class     A backed enum or a value class.
     * @param int          $min       The fewest items: editors cannot remove more. 0 or more.
     * @param int|null     $max       The most items: editors cannot add more, and only the first `max` stored items
     *                                are created. 1 or more and at least `min`; null for no limit.
     * @param string|null  $itemLabel What one item is, for the heading of an item without text, e.g. "Badge 2"; null
     *                                for the short name of the class in words (`SocialLink` becomes "Social link").
     * @param bool         $appender  Add items in the canvas of the block editor through a faded new item. Only for
     *                                lists of values in block attributes, not in list items.
     */
    public function __construct(
        public string $class,
        public int $min = 0,
        public ?int $max = null,
        public ?string $itemLabel = null,
        public bool $appender = false,
    ) {}
}

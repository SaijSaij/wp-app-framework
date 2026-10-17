<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Table;

use Attribute;

/**
 * Adds the column to an index. Without a group, the column gets an index of its own, named after the column; with a
 * group, it is part of the index called like the group, at its position (1, 2, …). Repeat the attribute to put a
 * column into several indexes.
 *
 * @api Attributes/Tables
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final readonly class Indexed
{
    /**
     * @param string|null $group    Name of an index of several columns.
     * @param int|null    $position Position of the column in the group, required with a group.
     * @param bool        $unique   No two rows with the same values (the same for every column of a group).
     */
    public function __construct(
        public ?string $group = null,
        public ?int $position = null,
        public bool $unique = false,
    ) {}
}

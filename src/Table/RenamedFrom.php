<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Table;

use Attribute;

/**
 * An earlier name of the parameter (the column is its name in snake case): an existing column of that name is renamed
 * and keeps its data. Repeat it for a chain of renames, newest first. Keep it as long as an installation may still
 * have the old column; removing it drops such a column with its data.
 *
 * @api Attributes/Tables
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final readonly class RenamedFrom
{
    /**
     * @param string $column The earlier name of the parameter, e.g. `text` or `postID`.
     */
    public function __construct(
        public string $column,
    ) {}
}

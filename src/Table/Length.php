<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Table;

use Attribute;

/**
 * The maximum length of a string column in characters (1 to 16383) instead of 191. Saving a longer string fails.
 *
 * @api Attributes/Tables
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Length
{
    /**
     * @param int $length The maximum number of characters, 1 to 16383; a default of the parameter must fit. The build
     *                    reports other values.
     */
    public function __construct(
        public int $length,
    ) {}
}

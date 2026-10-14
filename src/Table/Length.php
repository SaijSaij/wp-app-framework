<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Table;

use Attribute;

/**
 * The maximum length of a string column in characters (1 to 16383) instead of 191. Saving a longer string fails.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Length
{
    public function __construct(
        public int $length,
    ) {}
}

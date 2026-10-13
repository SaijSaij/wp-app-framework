<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Posts that meet all of the conditions: `All::of($a, $b)`, e.g. within Any.
 */
#[NoService]
final readonly class All extends Group
{
    public static function of(Condition ...$conditions): self
    {
        return new self(array_values($conditions));
    }

    public function getRelation(): string
    {
        return 'AND';
    }
}

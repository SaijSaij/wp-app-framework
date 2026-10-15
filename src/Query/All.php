<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Posts that meet all of the conditions: `All::of($a, $b)`, e.g. within Any.
 *
 * @api Posts and queries
 */
#[NoService]
final readonly class All extends Group
{
    /**
     * A group that is met if all of its conditions are met.
     *
     * @param Condition ...$conditions At least one condition, all of the same kind: all on meta fields, all on terms
     *                                 or all on the columns of a table; a group counts as the kind of its
     *                                 conditions.
     *
     * @throws \InvalidArgumentException Without conditions or with conditions of different kinds.
     */
    public static function of(Condition ...$conditions): self
    {
        return new self(array_values($conditions));
    }

    public function getRelation(): string
    {
        return 'AND';
    }
}

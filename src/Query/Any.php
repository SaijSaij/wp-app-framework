<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Posts that meet at least one of the conditions: `Any::of($a, $b)`.
 *
 * @api Posts and queries
 */
#[NoService]
final readonly class Any extends Group
{
    /**
     * A group that is met if at least one of its conditions is met.
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
        return 'OR';
    }
}

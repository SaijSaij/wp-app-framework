<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Posts that meet at least one of the conditions: `Any::of($a, $b)`.
 */
#[NoService]
final readonly class Any extends Group
{
    public static function of(Condition ...$conditions): self
    {
        return new self(array_values($conditions));
    }

    public function getRelation(): string
    {
        return 'OR';
    }
}

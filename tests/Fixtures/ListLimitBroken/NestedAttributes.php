<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ListLimitBroken;

use Saij\WpAppFramework\Value\ListOf;

final readonly class NestedAttributes
{
    /**
     * @param list<Row> $rows
     */
    public function __construct(
        #[ListOf(Row::class)]
        public array $rows = [],
    ) {}
}

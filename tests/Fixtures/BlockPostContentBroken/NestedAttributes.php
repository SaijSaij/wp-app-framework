<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use Saij\WpAppFramework\Value\ListOf;

final readonly class NestedAttributes
{
    /**
     * @param list<Inner> $list
     */
    public function __construct(
        public Inner $inner = new Inner(),
        #[ListOf(Inner::class)]
        public array $list = [],
    ) {}
}

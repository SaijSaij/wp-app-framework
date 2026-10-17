<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use WebCodingCat\WpAppFramework\Value\ListOf;

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

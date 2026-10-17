<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockBroken;

use WebCodingCat\WpAppFramework\Value\ListOf;

final readonly class RecursiveAttributes
{
    /**
     * @param list<Node> $nodes
     */
    public function __construct(
        #[ListOf(Node::class)]
        public array $nodes = [],
    ) {}
}

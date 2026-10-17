<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockBroken;

use WebCodingCat\WpAppFramework\Value\ListOf;

final readonly class Node
{
    /**
     * @param list<Node> $children
     */
    public function __construct(
        public string $title = '',
        #[ListOf(Node::class)]
        public array $children = [],
    ) {}
}

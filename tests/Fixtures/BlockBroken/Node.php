<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Value\ListOf;

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

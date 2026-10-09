<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Value\ListOf;

final readonly class RecursiveAttributes
{
    /**
     * @param list<Node> $nodes
     */
    public function __construct(
        #[ListOf(Node::class)] public array $nodes = [],
    ) {
    }
}

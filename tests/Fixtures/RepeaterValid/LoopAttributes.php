<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RepeaterValid;

use Saij\WpAppFramework\Value\PostReference;

final readonly class LoopAttributes
{
    /**
     * @param list<int> $posts
     */
    public function __construct(
        #[PostReference]
        public array $posts = [],
    ) {}
}

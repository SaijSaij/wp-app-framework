<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\RepeaterValid;

use WebCodingCat\WpAppFramework\Value\PostReference;

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

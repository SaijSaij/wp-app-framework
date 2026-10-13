<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\MetaFieldsBroken;

use Saij\WpAppFramework\Post\PostMeta;
use Saij\WpAppFramework\Value\ListOf;

#[PostMeta('details', title: 'Details', postTypes: [Show::class])]
final class ShowDetails
{
    /**
     * @param list<Kind> $tags
     */
    public function __construct(
        public int $seats = 0,
        public Kind $kind = Kind::Small,
        public ?Show $next = null,
        #[ListOf(Kind::class)]
        public array $tags = [],
    ) {}
}

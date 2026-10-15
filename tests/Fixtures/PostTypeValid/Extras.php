<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeValid;

use Saij\WpAppFramework\Post\Page;
use Saij\WpAppFramework\Post\Post;
use Saij\WpAppFramework\Post\PostMeta;
use Saij\WpAppFramework\Value\PlainText;

#[PostMeta('extras', title: 'Extras', postTypes: [Post::class, Page::class])]
final class Extras
{
    public function __construct(
        #[PlainText]
        public string $subtitle = '',
        public bool $featured = false,
    ) {}
}

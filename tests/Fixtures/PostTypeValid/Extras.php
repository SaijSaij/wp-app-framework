<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeValid;

use WebCodingCat\WpAppFramework\Post\Page;
use WebCodingCat\WpAppFramework\Post\Post;
use WebCodingCat\WpAppFramework\Post\PostMeta;
use WebCodingCat\WpAppFramework\Value\PlainText;

#[PostMeta('extras', title: 'Extras', postTypes: [Post::class, Page::class])]
final class Extras
{
    public function __construct(
        #[PlainText]
        public string $subtitle = '',
        public bool $featured = false,
    ) {}
}

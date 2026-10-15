<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\LinkBroken;

use Saij\WpAppFramework\Value\Content;
use Saij\WpAppFramework\Value\Image;
use Saij\WpAppFramework\Value\Link;
use Saij\WpAppFramework\Value\PlainText;
use Saij\WpAppFramework\Value\PostReference;
use Saij\WpAppFramework\Value\RichText;
use Saij\WpAppFramework\Value\Url;

final readonly class BrokenAttributes
{
    public function __construct(
        public ?Link $nullable = null,
        #[Content]
        public Link $content = new Link(),
        #[Url]
        public Link $url = new Link(),
        #[PlainText]
        public Link $plain = new Link(),
        #[RichText]
        public Link $rich = new Link(),
        #[Image]
        public Link $image = new Link(),
        #[PostReference]
        public Link $reference = new Link(),
    ) {}
}

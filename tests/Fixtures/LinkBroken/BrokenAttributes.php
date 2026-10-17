<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\LinkBroken;

use WebCodingCat\WpAppFramework\Value\Content;
use WebCodingCat\WpAppFramework\Value\Image;
use WebCodingCat\WpAppFramework\Value\Link;
use WebCodingCat\WpAppFramework\Value\PlainText;
use WebCodingCat\WpAppFramework\Value\PostReference;
use WebCodingCat\WpAppFramework\Value\RichText;
use WebCodingCat\WpAppFramework\Value\Url;

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

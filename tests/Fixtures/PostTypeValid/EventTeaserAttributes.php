<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeValid;

use WebCodingCat\WpAppFramework\Post\Category;
use WebCodingCat\WpAppFramework\Value\TermReference;

final readonly class EventTeaserAttributes
{
    public function __construct(
        public ?Event $event = null,
        #[TermReference(taxonomies: [Category::class, Genre::class])]
        public ?int $topic = null,
    ) {}
}

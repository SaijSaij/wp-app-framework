<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeValid;

use Saij\WpAppFramework\Post\Category;
use Saij\WpAppFramework\Value\TermReference;

final readonly class EventTeaserAttributes
{
    public function __construct(
        public ?Event $event = null,
        #[TermReference(taxonomies: [Category::class, Genre::class])]
        public ?int $topic = null,
    ) {}
}

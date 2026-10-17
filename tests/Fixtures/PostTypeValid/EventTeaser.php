<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Cache\Cache;
use WebCodingCat\WpAppFramework\Cache\Tag\PostTypeTag;
use WebCodingCat\WpAppFramework\Cache\Tag\TaxonomyTag;
use WebCodingCat\WpAppFramework\Post\Category;

/**
 * A block referring to a post object; cached by the post type and taxonomy classes.
 */
#[Block('event-teaser', title: 'Event teaser', category: BlockCategory::Widgets)]
#[Cache(ttl: 60, tags: [new PostTypeTag(Event::class), new TaxonomyTag(Genre::class), new TaxonomyTag(Category::class)])]
final class EventTeaser
{
    public function render(EventTeaserAttributes $attributes): string
    {
        return $attributes->event?->getTitle() ?? '';
    }
}

<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeValid;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Cache\Cache;
use Saij\WpAppFramework\Cache\Tag\PostTypeTag;
use Saij\WpAppFramework\Cache\Tag\TaxonomyTag;
use Saij\WpAppFramework\Post\Category;

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

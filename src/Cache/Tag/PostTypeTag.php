<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Tag;

use Saij\WpAppFramework\Cache\CacheTag;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Invalidated when a post of the post type is created, changed, trashed or
 * deleted, changes its status or its post meta. The post type is its name or a
 * post class of the theme or plugin (`new PostTypeTag(Event::class)`).
 *
 * @api Cache
 */
#[NoService]
final readonly class PostTypeTag implements CacheTag
{
    /**
     * @param string $postType The name of the post type (1 to 20 characters) or a post class of this theme or plugin
     *                         (`#[PostType]`, Post or Page), e.g. `Event::class`.
     */
    public function __construct(private string $postType) {}

    /**
     * The post type as given to the constructor: a name or a post class.
     */
    public function getPostType(): string
    {
        return $this->postType;
    }
}

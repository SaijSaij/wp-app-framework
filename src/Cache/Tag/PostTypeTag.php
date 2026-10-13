<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Tag;

use Saij\WpAppFramework\Cache\CacheTag;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Invalidated when a post of the post type is created, changed, trashed or
 * deleted, changes its status or its post meta. The post type is its name or a
 * post class of the theme or plugin (`new PostTypeTag(Event::class)`).
 */
#[NoService]
final readonly class PostTypeTag implements CacheTag
{
    public function __construct(private string $postType) {}

    public function getPostType(): string
    {
        return $this->postType;
    }
}

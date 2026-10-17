<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Value;

use Attribute;

/**
 * A reference to posts: on `?int` the ID of one post (null without), on
 * `array` a list of post IDs in a fixed order. Edited with a search in the
 * block inspector and on the settings page.
 *
 * When the value is created, a post that no longer exists, is of another post
 * type or is not publicly viewable becomes null (or is left out of the list);
 * renders in the block editor keep posts that are not public, so editors see
 * their choice.
 *
 * @api Attributes/Values
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class PostReference
{
    /**
     * @param list<string>|null $postTypes Post types to choose from (slugs, e.g. ["page"]); null for all viewable post types.
     */
    public function __construct(public ?array $postTypes = null) {}
}

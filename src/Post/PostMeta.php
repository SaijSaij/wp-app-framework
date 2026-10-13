<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Post;

use Attribute;

/**
 * Declares a class of post meta fields: each promoted constructor parameter is a
 * field, stored in its own meta key `{namespace}_{field}` (field name in snake
 * case). The fields are reached through the post object only:
 * `$event->meta(EventDetails::class)`.
 *
 * The class is not readonly: the fields are usually private, with getters and
 * setters, and `save()` of the post writes the changed values.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class PostMeta
{
    /**
     * @param string                          $name      Name of the class within each post type: lowercase letters,
     *                                                   digits and "_", starting with a letter (`post.meta('details')`
     *                                                   in templates).
     * @param string                          $title     Title of the panel in the block editor and of the meta box.
     * @param list<class-string<AbstractPost>> $postTypes Post classes the fields belong to: classes with #[PostType],
     *                                                   Post or Page.
     */
    public function __construct(
        public string $name,
        public string $title,
        public array $postTypes,
    ) {}
}

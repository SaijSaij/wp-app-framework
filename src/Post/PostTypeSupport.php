<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Post;

/**
 * Features of a post type (`supports` of register_post_type()). The framework adds
 * `custom-fields` itself when meta is registered for the type: without it, the REST
 * API has no `meta` field.
 */
enum PostTypeSupport: string
{
    case Title = 'title';
    case Editor = 'editor';
    case Author = 'author';
    case Thumbnail = 'thumbnail';
    case Excerpt = 'excerpt';
    case Comments = 'comments';
    case Trackbacks = 'trackbacks';
    case Revisions = 'revisions';
    case PageAttributes = 'page-attributes';
    case PostFormats = 'post-formats';
}

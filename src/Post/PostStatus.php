<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Post;

/**
 * The statuses of a post that WordPress registers itself.
 *
 * @api Enums
 */
enum PostStatus: string
{
    case Publish = 'publish';
    case Future = 'future';
    case Draft = 'draft';
    case Pending = 'pending';
    case Private = 'private';
    case Trash = 'trash';
}

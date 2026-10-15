<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Post;

/**
 * The post formats of WordPress, stored as terms `post-format-{value}` of the taxonomy `post_format`; Standard is a
 * post without such a term.
 *
 * @api Enums
 */
enum PostFormat: string
{
    case Standard = 'standard';
    case Aside = 'aside';
    case Chat = 'chat';
    case Gallery = 'gallery';
    case Link = 'link';
    case Image = 'image';
    case Quote = 'quote';
    case Status = 'status';
    case Video = 'video';
    case Audio = 'audio';
}

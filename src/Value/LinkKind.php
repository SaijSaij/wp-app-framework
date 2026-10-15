<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Value;

/**
 * What a link points to: a URL entered freely or a content object of the site
 * whose URL is resolved when the link is created from its stored value.
 *
 * @api Enums
 */
enum LinkKind: string
{
    /** A URL entered freely; the ID is 0. */
    case Url = 'url';

    /** A post of any viewable post type; the URL is its permalink. */
    case Post = 'post';

    /** A term of a viewable taxonomy; the URL is its archive. */
    case Term = 'term';

    /** A media file; the URL is the file. */
    case Attachment = 'attachment';
}

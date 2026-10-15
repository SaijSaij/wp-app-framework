<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

/**
 * Whether comments or pings of a post are open.
 *
 * @api Enums
 */
enum DiscussionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}

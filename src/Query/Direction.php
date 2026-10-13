<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

/**
 * The direction of a sort order.
 */
enum Direction: string
{
    case Asc = 'ASC';
    case Desc = 'DESC';
}

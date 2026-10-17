<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Query;

/**
 * The direction of a sort order.
 *
 * @api Enums
 */
enum Direction: string
{
    case Asc = 'ASC';
    case Desc = 'DESC';
}

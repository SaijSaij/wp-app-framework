<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cron;

/**
 * A day of the week, numbered as in ISO 8601 (PHP date format `N`).
 *
 * @api Enums
 */
enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;
}

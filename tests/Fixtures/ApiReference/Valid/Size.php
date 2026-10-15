<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ApiReference\Valid;

/**
 * Sizes of a widget.
 *
 * @api Enums
 */
enum Size: string
{
    /** The smallest size. */
    case Small = 'small';
    case Large = 'large';
}

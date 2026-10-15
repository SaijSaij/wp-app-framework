<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Value;

use Attribute;

/**
 * A reference to users: on `?int` the ID of one user (null without), on
 * `array` a list of user IDs in a fixed order. A user who no longer exists
 * becomes null (or is left out of the list).
 *
 * The search lists the users WordPress shows to the editing user: without the
 * capability `list_users`, only authors of published posts.
 *
 * @api Attributes/Values
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class UserReference
{
    public function __construct() {}
}

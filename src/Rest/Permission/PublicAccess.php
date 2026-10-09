<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Rest\Permission;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Everybody may call the route, including anonymous visitors.
 */
#[NoService]
final readonly class PublicAccess implements Permission
{
}

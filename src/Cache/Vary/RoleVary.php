<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Vary;

use Saij\WpAppFramework\Cache\CacheVary;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * The roles of the current user; none for visitors who are not logged in.
 *
 * @api Cache
 */
#[NoService]
final readonly class RoleVary implements CacheVary {}

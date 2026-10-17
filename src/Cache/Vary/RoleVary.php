<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Cache\Vary;

use WebCodingCat\WpAppFramework\Cache\CacheVary;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * The roles of the current user; none for visitors who are not logged in.
 *
 * @api Cache
 */
#[NoService]
final readonly class RoleVary implements CacheVary {}

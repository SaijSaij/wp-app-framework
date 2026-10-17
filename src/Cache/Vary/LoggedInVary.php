<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Cache\Vary;

use WebCodingCat\WpAppFramework\Cache\CacheVary;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Whether the visitor is logged in.
 *
 * @api Cache
 */
#[NoService]
final readonly class LoggedInVary implements CacheVary {}

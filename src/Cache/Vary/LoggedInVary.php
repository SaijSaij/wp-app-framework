<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Vary;

use Saij\WpAppFramework\Cache\CacheVary;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Whether the visitor is logged in.
 *
 * @api Cache
 */
#[NoService]
final readonly class LoggedInVary implements CacheVary {}

<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Vary;

use Saij\WpAppFramework\Cache\CacheVary;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * The ID of the current user; 0 for visitors who are not logged in.
 */
#[NoService]
final readonly class UserVary implements CacheVary {}

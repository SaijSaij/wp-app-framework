<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Vary;

use Saij\WpAppFramework\Cache\CacheVary;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * The path of the requested URL, without the query string.
 */
#[NoService]
final readonly class UrlVary implements CacheVary {}

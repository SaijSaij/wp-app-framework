<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Cache\Vary;

use WebCodingCat\WpAppFramework\Cache\CacheVary;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * The path of the requested URL, without the query string.
 *
 * @api Cache
 */
#[NoService]
final readonly class UrlVary implements CacheVary {}

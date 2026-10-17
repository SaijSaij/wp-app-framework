<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Cache;

/**
 * A circumstance a cache entry depends on: each variant adds its current value
 * to the cache key, so every value gets an entry of its own. Implemented by
 * the classes in WebCodingCat\WpAppFramework\Cache\Vary only.
 *
 * @api Cache
 */
interface CacheVary {}

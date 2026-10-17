<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Cache;

/**
 * Where cache entries are stored; the values of the filter
 * `wp_app_framework/cache/backends/{slug}`.
 *
 * @internal
 */
enum CacheBackend: string
{
    /** The persistent object cache (an object-cache.php drop-in such as Redis). */
    case Object = 'object';

    /** PHP files below wp-content/cache/{slug}/data. */
    case File = 'file';

    /** Transients in the database. */
    case Transient = 'transient';
}

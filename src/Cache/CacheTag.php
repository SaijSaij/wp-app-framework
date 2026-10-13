<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache;

/**
 * A tag that invalidates cache entries.
 *
 * The framework provides PostTypeTag, TaxonomyTag and SettingsTag, which it
 * invalidates itself. Tags of your own are string-backed enums implementing
 * this interface; invalidate them with CacheStore::invalidate().
 */
interface CacheTag {}

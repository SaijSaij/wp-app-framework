<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache;

use Attribute;

/**
 * Caches the output of a block (on a `#[Block]` class) or the response data of
 * a REST route (on a `#[RestRoute]` method that answers only GET).
 *
 * The entry depends on the inputs (block attributes, inner content and context
 * values; URL parameters and arguments of a route), on the current value of
 * each variant in `vary` and on the code. It expires after `ttl` seconds or
 * when one of its tags is invalidated, whichever comes first.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Cache
{
    /**
     * @param int             $ttl  Lifetime in seconds, at least 1.
     * @param list<CacheTag>  $tags Invalidate the entry: PostTypeTag, TaxonomyTag, SettingsTag or an enum of your own.
     * @param list<CacheVary> $vary Circumstances the entry depends on, e.g. new UserVary() or new QueryParamVary('page').
     */
    public function __construct(
        public int $ttl,
        public array $tags = [],
        public array $vary = [],
    ) {}
}

<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Tag;

use Saij\WpAppFramework\Cache\CacheTag;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Invalidated when a term of the taxonomy is created, changed or deleted,
 * changes its term meta, or is assigned to or removed from an object.
 */
#[NoService]
final readonly class TaxonomyTag implements CacheTag
{
    public function __construct(private string $taxonomy) {}

    public function getTaxonomy(): string
    {
        return $this->taxonomy;
    }
}

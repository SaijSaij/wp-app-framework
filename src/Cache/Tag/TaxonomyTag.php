<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Tag;

use Saij\WpAppFramework\Cache\CacheTag;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Invalidated when a term of the taxonomy is created, changed or deleted,
 * changes its term meta, or is assigned to or removed from an object. The
 * taxonomy is its name or a taxonomy class of the theme or plugin
 * (`new TaxonomyTag(Genre::class)`).
 *
 * @api Cache
 */
#[NoService]
final readonly class TaxonomyTag implements CacheTag
{
    /**
     * @param string $taxonomy The name of the taxonomy (1 to 32 characters) or a taxonomy class: `#[Taxonomy]` of this
     *                         theme or plugin, Category or Tag.
     */
    public function __construct(private string $taxonomy) {}

    /**
     * The taxonomy as given to the constructor: a name or a taxonomy class.
     */
    public function getTaxonomy(): string
    {
        return $this->taxonomy;
    }
}

<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Post\Taxonomy;

#[Taxonomy('with_fields', label: 'Items', singularLabel: 'Item', postTypes: [Valid::class])]
final class TaxonomyProblems
{
    public function __construct(private int $count = 0) {}

    public function getCount(): int
    {
        return $this->count;
    }
}

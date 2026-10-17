<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Post\Taxonomy;

#[Taxonomy('a_taxonomy_name_that_is_too_long', label: 'Items', singularLabel: 'Item', postTypes: [Valid::class])]
final class TaxonomyTooLong {}

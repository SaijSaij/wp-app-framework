<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Post\AbstractPost;
use WebCodingCat\WpAppFramework\Post\PostType;
use WebCodingCat\WpAppFramework\Post\Taxonomy;

#[PostType('combined', label: 'Items', singularLabel: 'Item')]
#[Taxonomy('combined', label: 'Items', singularLabel: 'Item', postTypes: [Valid::class])]
final class Combined extends AbstractPost {}

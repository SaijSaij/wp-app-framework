<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Post\AbstractPost;
use Saij\WpAppFramework\Post\PostType;
use Saij\WpAppFramework\Post\Taxonomy;

#[PostType('combined', label: 'Items', singularLabel: 'Item')]
#[Taxonomy('combined', label: 'Items', singularLabel: 'Item', postTypes: [Valid::class])]
final class Combined extends AbstractPost {}

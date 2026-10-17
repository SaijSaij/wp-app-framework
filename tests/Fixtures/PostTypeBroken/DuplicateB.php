<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Post\AbstractPost;
use WebCodingCat\WpAppFramework\Post\PostType;

#[PostType('twice', label: 'Items', singularLabel: 'Item')]
final class DuplicateB extends AbstractPost {}

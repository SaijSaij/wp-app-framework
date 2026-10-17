<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Post\PostType;

#[PostType('not_a_post', label: 'Items', singularLabel: 'Item')]
final class NotAPost {}

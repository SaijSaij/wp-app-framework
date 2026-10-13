<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Post\PostType;

#[PostType('not_a_post', label: 'Items', singularLabel: 'Item')]
final class NotAPost {}

<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Post\AbstractPost;
use Saij\WpAppFramework\Post\PostType;

#[PostType('twice', label: 'Items', singularLabel: 'Item')]
final class DuplicateA extends AbstractPost {}

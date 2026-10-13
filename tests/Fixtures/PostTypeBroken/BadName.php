<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Post\AbstractPost;
use Saij\WpAppFramework\Post\PostType;

#[PostType('Bad-Name', label: 'Items', singularLabel: 'Item')]
final class BadName extends AbstractPost {}

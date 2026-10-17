<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Post\AbstractPost;
use WebCodingCat\WpAppFramework\Post\PostType;

#[PostType('Bad-Name', label: 'Items', singularLabel: 'Item')]
final class BadName extends AbstractPost {}

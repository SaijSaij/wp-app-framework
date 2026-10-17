<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Post\AbstractPost;
use WebCodingCat\WpAppFramework\Post\PostType;

#[PostType('bad_slug', label: 'Items', singularLabel: 'Item', slug: 'Bad Slug')]
final class BadSlug extends AbstractPost {}

<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Post\AbstractPost;
use Saij\WpAppFramework\Post\PostType;

#[PostType('bad_slug', label: 'Items', singularLabel: 'Item', slug: 'Bad Slug')]
final class BadSlug extends AbstractPost {}

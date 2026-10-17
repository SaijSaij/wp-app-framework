<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeValid;

use WebCodingCat\WpAppFramework\Post\Post;
use WebCodingCat\WpAppFramework\Post\Taxonomy;

#[Taxonomy('genre', label: 'Genres', singularLabel: 'Genre', postTypes: [Event::class, Post::class], hierarchical: true)]
final class Genre {}

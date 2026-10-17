<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeValid;

use WebCodingCat\WpAppFramework\Post\AbstractPost;
use WebCodingCat\WpAppFramework\Post\PostType;
use WebCodingCat\WpAppFramework\Post\PostTypeSupport;

/**
 * A data type: not public, no editor, a slug of its own.
 */
#[PostType('venue', label: 'Venues', singularLabel: 'Venue', public: false, supports: [PostTypeSupport::Title, PostTypeSupport::PageAttributes], hierarchical: true, slug: 'places')]
final class Venue extends AbstractPost {}

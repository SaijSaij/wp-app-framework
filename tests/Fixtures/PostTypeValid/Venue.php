<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeValid;

use Saij\WpAppFramework\Post\AbstractPost;
use Saij\WpAppFramework\Post\PostType;
use Saij\WpAppFramework\Post\PostTypeSupport;

/**
 * A data type: not public, no editor, a slug of its own.
 */
#[PostType('venue', label: 'Venues', singularLabel: 'Venue', public: false, supports: [PostTypeSupport::Title, PostTypeSupport::PageAttributes], hierarchical: true, slug: 'places')]
final class Venue extends AbstractPost {}

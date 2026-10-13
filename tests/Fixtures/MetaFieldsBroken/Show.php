<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\MetaFieldsBroken;

use Saij\WpAppFramework\Post\AbstractPost;
use Saij\WpAppFramework\Post\PostType;

#[PostType('show', label: 'Shows', singularLabel: 'Show')]
final class Show extends AbstractPost {}

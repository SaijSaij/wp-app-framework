<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Post\AbstractPost;
use WebCodingCat\WpAppFramework\Post\PostType;

#[PostType('valid', label: 'Valids', singularLabel: 'Valid')]
final class Valid extends AbstractPost {}

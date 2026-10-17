<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Post\AbstractPost;
use WebCodingCat\WpAppFramework\Post\PostType;

#[PostType('with_property', label: 'Items', singularLabel: 'Item')]
final class WithProperty extends AbstractPost
{
    private string $cached = '';

    public function getCached(): string
    {
        return $this->cached;
    }
}

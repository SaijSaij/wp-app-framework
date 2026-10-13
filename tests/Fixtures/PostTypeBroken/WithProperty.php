<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Post\AbstractPost;
use Saij\WpAppFramework\Post\PostType;

#[PostType('with_property', label: 'Items', singularLabel: 'Item')]
final class WithProperty extends AbstractPost
{
    private string $cached = '';

    public function getCached(): string
    {
        return $this->cached;
    }
}

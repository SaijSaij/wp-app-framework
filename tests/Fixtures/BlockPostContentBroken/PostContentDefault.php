<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('fixture/post-content-default', title: 'PostContentDefault', category: BlockCategory::Text)]
final readonly class PostContentDefault
{
    public function render(PostContentDefaultAttributes $attrs): string
    {
        return '';
    }
}

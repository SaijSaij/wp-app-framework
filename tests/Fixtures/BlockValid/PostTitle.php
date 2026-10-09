<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockValid;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use WP_Block;

#[Block('fixture/post-title', title: 'Post title', category: BlockCategory::Theme, usesContext: ['postId'])]
final class PostTitle
{
    public function render(WP_Block $block): string
    {
        return 'title of ' . $block->getName();
    }
}

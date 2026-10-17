<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WP_Block;

#[Block('post-title', title: 'Post title', category: BlockCategory::Theme, usesContext: ['postId'])]
final class PostTitle
{
    public function render(WP_Block $block): string
    {
        return 'title of ' . $block->name;
    }
}

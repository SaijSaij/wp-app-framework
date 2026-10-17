<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('post-content-default', title: 'PostContentDefault', category: BlockCategory::Text)]
final readonly class PostContentDefault
{
    public function render(PostContentDefaultAttributes $attrs): string
    {
        return '';
    }
}

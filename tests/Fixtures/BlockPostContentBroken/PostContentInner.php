<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('post-content-inner', title: 'PostContentInner', category: BlockCategory::Text)]
final readonly class PostContentInner
{
    public function render(PostContentInnerAttributes $attrs, string $content): string
    {
        return '';
    }
}

<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('post-content-inner', title: 'PostContentInner', category: BlockCategory::Text)]
final readonly class PostContentInner
{
    public function render(PostContentInnerAttributes $attrs, string $content): string
    {
        return '';
    }
}

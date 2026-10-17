<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ContainerBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\InnerBlocks;

#[Block('direct', title: 'Direct', category: BlockCategory::Text)]
#[InnerBlocks(allowed: ['core/paragraph'], directInsert: true)]
final class Direct
{
    public function render(string $content): string
    {
        return $content;
    }
}

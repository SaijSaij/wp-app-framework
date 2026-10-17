<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ContainerBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\InnerBlocks;

#[Block('leaf', title: 'Leaf', category: BlockCategory::Text)]
#[InnerBlocks]
final class Leaf
{
    public function render(): string
    {
        return '';
    }
}

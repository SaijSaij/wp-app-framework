<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ContainerBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\InnerBlocks;

#[Block('leaf', title: 'Leaf', category: BlockCategory::Text)]
#[InnerBlocks]
final class Leaf
{
    public function render(): string
    {
        return '';
    }
}
